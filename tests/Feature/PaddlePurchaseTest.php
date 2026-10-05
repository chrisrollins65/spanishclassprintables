<?php

namespace Tests\Feature;

use App\Models\CreditEntry;
use App\Models\CreditUnit;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Buying credits, and what a refund takes back (docs/teacher-games.md, Phase 2).
 */
class PaddlePurchaseTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'pdl_ntfset_test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paddle.webhook_secret' => self::SECRET,
            'paddle.credits' => ['pri_single' => 1, 'pri_bundle' => 10],
        ]);
    }

    /** Posts a webhook the way Paddle does: signed over the raw body with a timestamp. */
    private function send(array $payload, ?string $secret = null, ?int $ts = null): TestResponse
    {
        $body = json_encode($payload);
        $ts ??= time();
        $signature = hash_hmac('sha256', $ts.':'.$body, $secret ?? self::SECRET);

        return $this->call(
            'POST', '/api/paddle/webhook', [], [], [],
            ['HTTP_PADDLE_SIGNATURE' => "ts={$ts};h1={$signature}", 'CONTENT_TYPE' => 'application/json'],
            $body,
        );
    }

    private function sale(User $user, string $transaction = 'txn_1', string $price = 'pri_bundle'): array
    {
        return [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => $transaction,
                'custom_data' => ['user_id' => $user->id],
                'items' => [['price' => ['id' => $price], 'quantity' => 1]],
            ],
        ];
    }

    private function refund(string $transaction = 'txn_1', string $adjustment = 'adj_1', string $status = 'approved'): array
    {
        return [
            'event_type' => 'adjustment.created',
            'data' => ['id' => $adjustment, 'action' => 'refund', 'status' => $status, 'transaction_id' => $transaction],
        ];
    }

    public function test_the_credits_page_shows_what_is_on_sale(): void
    {
        config([
            'paddle.client_token' => 'test_token',
            'paddle.credits' => ['pri_single01' => 1, 'pri_bundle01' => 10],
        ]);
        $user = User::factory()->create();
        $user->grantCredits(3, CreditEntry::ADMIN, 'gift-1', 'A gift');

        $this->actingAs($user)->get('/credits')
            ->assertOk()
            ->assertSee('pri_single01', false)
            ->assertSee('pri_bundle01', false)
            ->assertSee('10 games')
            // The teacher's id rides along so the webhook knows whose money it is.
            ->assertSee('customData: { user_id: "'.$user->id.'"', false)
            ->assertSee('>3<', false);
    }

    public function test_the_credits_page_says_so_when_buying_is_not_set_up(): void
    {
        config(['paddle.client_token' => '', 'paddle.credits' => ['pri_placeholder' => 1]]);

        $this->actingAs(User::factory()->create())->get('/credits')
            ->assertOk()
            ->assertSee('switched on here yet')
            ->assertDontSee('cdn.paddle.com', false);
    }

    public function test_a_price_id_that_is_not_configured_is_not_offered(): void
    {
        // The defaults in config/paddle.php are placeholders, not prices.
        config(['paddle.client_token' => 'test_token', 'paddle.credits' => ['pri_single' => 1, 'not_a_price' => 10]]);

        $this->actingAs(User::factory()->create())->get('/credits')
            ->assertOk()
            ->assertDontSee('not_a_price', false);
    }

    public function test_the_balance_is_only_ever_your_own(): void
    {
        $user = User::factory()->create();
        $user->grantCredits(2, CreditEntry::ADMIN, 'gift-2', 'A gift');

        // Logged out first: actingAs lasts for the rest of the test.
        $this->getJson('/credits/balance')->assertUnauthorized();

        $this->actingAs($user)->getJson('/credits/balance')->assertOk()->assertJson(['credits' => 2]);
    }

    /**
     * The bug this guards: the page showed the ledger's sum, which counts
     * money in and never a game made. A teacher who had spent all three was
     * told they had three, and then sent here when they pressed create.
     */
    public function test_the_balance_is_what_is_left_not_what_was_bought(): void
    {
        $user = User::factory()->create();
        $user->grantCredits(3, CreditEntry::ADMIN, 'gift-3', 'A gift');

        $spent = TeacherGame::factory()->create(['user_id' => $user->id, 'published_at' => now()]);
        CreditUnit::claimFor($user, $spent);
        $draft = TeacherGame::factory()->draft()->create(['user_id' => $user->id]);
        CreditUnit::claimFor($user, $draft);

        $this->assertSame(3, $user->creditsPurchased());
        $this->assertSame(1, $user->availableCredits());

        $this->actingAs($user)->getJson('/credits/balance')->assertOk()->assertJson(['credits' => 1]);

        // And the page agrees with it, with the draft's credit named rather
        // than just missing from the count.
        config(['paddle.client_token' => 'test_token', 'paddle.credits' => ['pri_single01' => 1]]);
        $this->actingAs($user)->get('/credits')
            ->assertOk()
            ->assertSee('>1<', false)
            ->assertSee('credit ready to use')
            ->assertSee('held by a draft');
    }

    public function test_a_sale_becomes_credits(): void
    {
        $user = User::factory()->create();

        $this->send($this->sale($user))->assertOk();

        $this->assertSame(10, $user->creditsPurchased());
    }

    public function test_the_same_delivery_twice_is_credited_once(): void
    {
        // Paddle retries anything it does not hear back from.
        $user = User::factory()->create();

        $this->send($this->sale($user))->assertOk();
        $this->send($this->sale($user))->assertOk();

        $this->assertSame(10, $user->creditsPurchased());
        $this->assertSame(1, CreditEntry::where('reason', CreditEntry::PURCHASE)->count());
    }

    public function test_an_unsigned_or_stale_delivery_buys_nothing(): void
    {
        $user = User::factory()->create();

        $this->send($this->sale($user), 'the-wrong-secret')->assertForbidden();
        // A body captured once must not work tomorrow.
        $this->send($this->sale($user), null, time() - 3600)->assertForbidden();
        $this->send($this->sale($user), null, time() + 3600)->assertForbidden();
        $this->postJson('/api/paddle/webhook', $this->sale($user))->assertForbidden();

        $this->assertSame(0, $user->creditsPurchased());
    }

    public function test_a_delivery_signed_during_a_secret_rotation_is_accepted(): void
    {
        // Paddle signs with both the old and the new secret while a secret is
        // being rotated, sending more than one h1.
        $user = User::factory()->create();
        $body = json_encode($this->sale($user));
        $ts = time();
        $header = "ts={$ts};h1=".hash_hmac('sha256', $ts.':'.$body, 'the-old-secret')
            .';h1='.hash_hmac('sha256', $ts.':'.$body, self::SECRET);

        $this->call(
            'POST', '/api/paddle/webhook', [], [], [],
            ['HTTP_PADDLE_SIGNATURE' => $header, 'CONTENT_TYPE' => 'application/json'],
            $body,
        )->assertOk();

        $this->assertSame(10, $user->creditsPurchased());
    }

    public function test_a_price_we_do_not_sell_credits_nothing(): void
    {
        $user = User::factory()->create();

        $this->send($this->sale($user, 'txn_x', 'pri_something_else'))->assertOk();

        $this->assertSame(0, $user->creditsPurchased());
    }

    public function test_a_refund_takes_the_credits_back(): void
    {
        $user = User::factory()->create();
        $this->send($this->sale($user));

        $this->send($this->refund())->assertOk();

        $this->assertSame(0, $user->creditsPurchased());
    }

    public function test_a_refund_still_under_review_takes_nothing(): void
    {
        $user = User::factory()->create();
        $this->send($this->sale($user));

        $this->send($this->refund(status: 'pending_approval'))->assertOk();

        $this->assertSame(10, $user->creditsPurchased());
    }

    public function test_a_sale_hands_over_credits_to_spend(): void
    {
        $user = User::factory()->create();

        $this->send($this->sale($user))->assertOk();

        // Ten on the ledger, and ten things a teacher can actually spend.
        $this->assertSame(10, $user->creditsPurchased());
        $this->assertSame(10, $user->availableCredits());
    }

    public function test_a_refund_takes_back_a_published_game_and_leaves_a_draft_standing(): void
    {
        $user = User::factory()->create();
        $this->send($this->sale($user, 'txn_1', 'pri_single'));

        // Published: the credit was spent on it.
        $published = TeacherGame::factory()->create(['user_id' => $user->id, 'published_at' => now()]);
        CreditUnit::claimFor($user, $published);

        $claimed = TeacherGame::factory()->create(['user_id' => $user->id, 'source_code' => 'ABC123']);

        $this->send($this->refund())->assertOk();

        // Out of the teacher's list, but kept: refunds get reversed, and an
        // admin can give it back.
        $this->assertSoftDeleted($published);
        $this->assertTrue($published->fresh()->isLocked());
        $this->assertSame(0, $user->availableCredits());

        // A game claimed from a TpT pack was never paid for here.
        $this->assertFalse($claimed->fresh()->isLocked());
    }

    public function test_a_refund_only_takes_the_credits_that_purchase_made(): void
    {
        $user = User::factory()->create();
        $this->send($this->sale($user, 'txn_1', 'pri_single'));   // bought one
        $user->grantCredits(2, CreditEntry::ADMIN, 'gift-1', 'Sorry about that');

        $this->send($this->refund())->assertOk();

        // The apology survives the refund of something else entirely.
        $this->assertSame(2, $user->availableCredits());
    }

    public function test_a_refund_spares_a_working_game_while_it_can(): void
    {
        $user = User::factory()->create();
        $this->send($this->sale($user, 'txn_1', 'pri_bundle'));   // ten credits

        $published = TeacherGame::factory()->create(['user_id' => $user->id, 'published_at' => now()]);
        CreditUnit::claimFor($user, $published);

        $this->send($this->refund())->assertOk();

        // Ten credits back for ten refunded: nine were free, so only the last
        // one had to come out of a game she is using.
        $this->assertSoftDeleted($published);
        $this->assertSame(0, $user->availableCredits());
    }

    public function test_a_refund_takes_the_credit_out_from_under_a_draft(): void
    {
        $user = User::factory()->create();
        $this->send($this->sale($user, 'txn_1', 'pri_single'));

        $draft = TeacherGame::factory()->draft()->create(['user_id' => $user->id]);
        CreditUnit::claimFor($user, $draft);

        $this->send($this->refund())->assertOk();

        // The writing stays — a draft costs nothing to keep — but it is no
        // longer paid for, and publishing will ask for a credit.
        $this->assertNotSoftDeleted($draft);
        $this->assertNull($draft->fresh()->creditUnit);
        $this->assertSame(0, $user->availableCredits());
    }

    public function test_a_locked_game_refuses_everything_but_the_list(): void
    {
        $game = TeacherGame::factory()->create();
        $game->lock('Refund of txn_1');

        $this->actingAs($game->user)->get("/my-games/{$game->id}/play")->assertForbidden();
        $this->actingAs($game->user)->get("/my-games/{$game->id}/payload.json")->assertForbidden();
        $this->actingAs($game->user)->get("/my-games/{$game->id}/edit")->assertForbidden();
        $this->actingAs($game->user)->postJson("/my-games/{$game->id}/cards", ['size' => 4])->assertForbidden();

        // It stays on the list, says why, and can still be removed by its owner.
        $this->actingAs($game->user)->get('/my-games')->assertOk()->assertSee('Locked');
        $this->actingAs($game->user)->delete("/my-games/{$game->id}")->assertRedirect('/my-games');
    }

    public function test_unlocking_gives_the_game_back(): void
    {
        $game = TeacherGame::factory()->create();
        $game->lock('Refund of txn_1');

        $game->unlock();

        $this->actingAs($game->user)->get("/my-games/{$game->id}/edit")->assertOk();
    }
}
