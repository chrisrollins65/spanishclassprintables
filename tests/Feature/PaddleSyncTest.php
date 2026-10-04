<?php

namespace Tests\Feature;

use App\Models\CreditEntry;
use App\Models\CreditUnit;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reading Paddle to put right what a webhook missed (php artisan paddle:sync).
 */
class PaddleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paddle.api_key' => 'pdl_test',
            'paddle.api_url' => 'https://sandbox-api.paddle.com',
            'paddle.credits' => ['pri_bundle' => 10],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @param  list<array<string, mixed>>  $adjustments
     */
    private function paddleHas(array $transactions = [], array $adjustments = []): void
    {
        Http::fake([
            '*/transactions*' => Http::response(['data' => $transactions, 'meta' => ['pagination' => ['has_more' => false]]]),
            '*/adjustments*' => Http::response(['data' => $adjustments, 'meta' => ['pagination' => ['has_more' => false]]]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sale(User $user, string $id = 'txn_1'): array
    {
        return [
            'id' => $id,
            'status' => 'completed',
            'custom_data' => ['user_id' => (string) $user->id],
            'items' => [['price' => ['id' => 'pri_bundle'], 'quantity' => 1]],
        ];
    }

    public function test_a_purchase_the_webhook_never_delivered_is_found(): void
    {
        $user = User::factory()->create();
        $this->paddleHas([$this->sale($user)]);

        $this->artisan('paddle:sync')->assertSuccessful();

        $this->assertSame(10, $user->creditsPurchased());
        $this->assertSame('txn_1', CreditEntry::sole()->reference);
    }

    public function test_pretending_reports_without_touching_anything(): void
    {
        $user = User::factory()->create();
        $this->paddleHas([$this->sale($user)]);

        $this->artisan('paddle:sync', ['--pretend' => true])
            ->expectsOutputToContain('missing')
            ->assertSuccessful();

        $this->assertSame(0, $user->creditsPurchased());
    }

    public function test_running_it_twice_grants_nothing_twice(): void
    {
        // The same rows every hour, for as long as the window reaches them.
        $user = User::factory()->create();
        $this->paddleHas([$this->sale($user)]);

        $this->artisan('paddle:sync');
        $this->artisan('paddle:sync');

        $this->assertSame(10, $user->creditsPurchased());
        $this->assertSame(1, CreditEntry::count());
    }

    public function test_a_purchase_already_credited_by_its_webhook_is_left_alone(): void
    {
        $user = User::factory()->create();
        $user->grantCredits(10, CreditEntry::PURCHASE, 'txn_1', 'Paddle transaction');
        $this->paddleHas([$this->sale($user)]);

        $this->artisan('paddle:sync')->expectsOutputToContain('Nothing missing');

        $this->assertSame(10, $user->creditsPurchased());
    }

    public function test_a_refund_the_webhook_missed_takes_its_credits_back(): void
    {
        $user = User::factory()->create();
        $user->grantCredits(10, CreditEntry::PURCHASE, 'txn_1', 'Paddle transaction');
        $game = TeacherGame::factory()->create(['user_id' => $user->id, 'published_at' => now()]);
        CreditUnit::claimFor($user, $game);

        $this->paddleHas(
            [$this->sale($user)],
            [['id' => 'adj_1', 'action' => 'refund', 'status' => 'approved', 'transaction_id' => 'txn_1']],
        );

        $this->artisan('paddle:sync')->assertSuccessful();

        $this->assertSame(0, $user->creditsPurchased());
        // Nine of the ten were never spent; the one that was is taken back.
        $this->assertTrue($game->fresh()->isLocked());
        $this->assertSame(0, $user->availableCredits());
    }

    public function test_a_sale_with_nobody_attached_is_left_for_a_person_to_judge(): void
    {
        // A test made in Paddle's own dashboard has no teacher on it.
        $this->paddleHas([[
            'id' => 'txn_orphan',
            'status' => 'completed',
            'items' => [['price' => ['id' => 'pri_bundle'], 'quantity' => 1]],
        ]]);

        $this->artisan('paddle:sync')->assertSuccessful();

        $this->assertSame(0, CreditEntry::count());
    }

    public function test_it_says_so_when_paddle_is_not_configured(): void
    {
        config(['paddle.api_key' => '']);

        $this->artisan('paddle:sync')->assertFailed();
    }
}
