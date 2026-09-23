<?php

namespace Tests\Feature;

use App\Models\CreditEntry;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shop's back office (docs/teacher-games.md, Phase 2).
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'shop@example.com']);
        config(['site.admins' => ['shop@example.com']]);

        return $user;
    }

    public function test_only_an_admin_gets_in(): void
    {
        $teacher = User::factory()->create();
        config(['site.admins' => ['shop@example.com']]);

        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($teacher)->get('/admin')->assertForbidden();
        $this->actingAs($teacher)->get("/admin/teachers/{$teacher->id}")->assertForbidden();

        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_admin_rights_come_from_the_config_not_the_row(): void
    {
        // Nothing a teacher can write to their own row can promote them.
        $teacher = User::factory()->create(['name' => 'is_admin']);
        config(['site.admins' => []]);

        $this->assertFalse($teacher->isAdmin());
        $this->actingAs($teacher)->get('/admin')->assertForbidden();
    }

    public function test_a_teachers_page_shows_the_ledger_and_their_games(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create(['name' => 'Ana Profe']);
        CreditEntry::record($teacher, 10, CreditEntry::PURCHASE, 'txn_1', 'Paddle transaction');
        TeacherGame::factory()->create(['user_id' => $teacher->id, 'theme' => 'Los Deportes']);

        $this->actingAs($admin)
            ->get("/admin/teachers/{$teacher->id}")
            ->assertOk()
            ->assertSee('Ana Profe')
            ->assertSee('10 credits')
            ->assertSee('txn_1')
            ->assertSee('Los Deportes');
    }

    public function test_credits_can_be_given_by_hand_with_a_reason(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();

        $this->actingAs($admin)
            ->from("/admin/teachers/{$teacher->id}")
            ->post("/admin/teachers/{$teacher->id}/credits", ['delta' => 3, 'note' => 'Sale that never webhooked'])
            ->assertRedirect("/admin/teachers/{$teacher->id}");

        $this->assertSame(3, $teacher->credits());
        $this->assertSame(CreditEntry::ADMIN, CreditEntry::where('user_id', $teacher->id)->sole()->reason);
    }

    public function test_a_reason_is_required_and_zero_is_refused(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();

        $this->actingAs($admin)->post("/admin/teachers/{$teacher->id}/credits", ['delta' => 3])
            ->assertSessionHasErrors('note');
        $this->actingAs($admin)->post("/admin/teachers/{$teacher->id}/credits", ['delta' => 0, 'note' => 'nothing'])
            ->assertSessionHasErrors('delta');

        $this->assertSame(0, $teacher->credits());
    }

    public function test_refunding_asks_paddle_and_waits_for_its_word(): void
    {
        Http::fake(['*/adjustments' => Http::response(['data' => ['id' => 'adj_1', 'status' => 'pending_approval']], 200)]);
        config(['paddle.api_key' => 'pdl_test', 'paddle.api_url' => 'https://api.paddle.com']);

        $admin = $this->admin();
        $teacher = User::factory()->create();
        $purchase = CreditEntry::record($teacher, 10, CreditEntry::PURCHASE, 'txn_1', 'Paddle transaction');

        $this->actingAs($admin)
            ->from("/admin/teachers/{$teacher->id}")
            ->post("/admin/purchases/{$purchase->id}/refund", ['reason' => 'Asked for it'])
            ->assertRedirect("/admin/teachers/{$teacher->id}");

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.paddle.com/adjustments'
            && $request['action'] === 'refund'
            && $request['transaction_id'] === 'txn_1');

        // The credits come off when Paddle says the refund is approved, not
        // when we ask — so the ledger has not moved yet.
        $this->assertSame(10, $teacher->credits());
    }

    public function test_only_a_purchase_can_be_refunded(): void
    {
        config(['paddle.api_key' => 'pdl_test']);
        Http::fake();
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $gift = CreditEntry::record($teacher, 2, CreditEntry::ADMIN, 'admin-1', 'A gift');

        $this->actingAs($admin)
            ->post("/admin/purchases/{$gift->id}/refund", ['reason' => 'Asked for it'])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_a_game_can_be_locked_and_given_back(): void
    {
        $admin = $this->admin();
        $game = TeacherGame::factory()->create();

        $this->actingAs($admin)->post("/admin/games/{$game->id}/lock", ['reason' => 'Chargeback'])->assertRedirect();
        $this->assertTrue($game->fresh()->isLocked());
        $this->actingAs($game->user)->get("/my-games/{$game->id}/play")->assertForbidden();

        $this->actingAs($admin)->post("/admin/games/{$game->id}/unlock")->assertRedirect();
        $this->assertFalse($game->fresh()->isLocked());
        $this->actingAs($game->user)->get("/my-games/{$game->id}/play")->assertOk();
    }
}
