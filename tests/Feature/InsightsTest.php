<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\GameEvent;
use App\Models\Room;
use App\Models\TeacherGame;
use App\Models\User;
use App\Support\Insights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin's Insights page, and the AI figures it reads.
 */
class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['site.admins' => ['shop@example.com']]);

        return User::factory()->create(['email' => 'shop@example.com']);
    }

    public function test_only_an_admin_gets_in(): void
    {
        $this->admin();

        $this->get('/admin/insights')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/insights')->assertForbidden();
    }

    public function test_an_empty_site_still_draws(): void
    {
        $this->actingAs($this->admin())->get('/admin/insights')->assertOk()->assertSee('No feedback yet.');
    }

    public function test_it_counts_plays_finishes_and_ratings(): void
    {
        Room::create(['code' => 'ABCD', 'theme' => 'La familia', 'payload' => '{}']);
        GameEvent::factory()->count(4)->create(['event' => 'game_started', 'game' => 'bingo']);
        GameEvent::factory()->create(['event' => 'game_finished', 'game' => 'bingo', 'room_code' => 'ABCD', 'data' => ['duration' => 1800]]);
        Feedback::factory()->create(['room_code' => 'ABCD', 'rating' => 1, 'comment' => 'The audio cut out']);

        $this->actingAs($this->admin())->get('/admin/insights')
            ->assertOk()
            ->assertSeeInOrder(['Games started', 'Finished (1)'])
            ->assertSee('25%')
            ->assertSee('30 min')
            ->assertSee('La familia')
            ->assertSee('The audio cut out');
    }

    public function test_the_period_leaves_out_what_is_older(): void
    {
        GameEvent::factory()->create(['event' => 'code_not_found', 'data' => ['code' => 'OLDONE'], 'created_at' => now()->subDays(60)]);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/insights')->assertDontSee('OLDONE');
        $this->actingAs($admin)->get('/admin/insights?days=90')->assertSee('OLDONE');
    }

    public function test_spam_stays_off_the_page(): void
    {
        Feedback::factory()->create(['comment' => 'buy pills', 'spam_reason' => 'too many links']);

        $this->actingAs($this->admin())->get('/admin/insights')->assertDontSee('buy pills');
    }

    public function test_the_hardest_clue_is_the_one_nobody_gets(): void
    {
        foreach ([false, false, false] as $answered) {
            GameEvent::factory()->create(['event' => 'clue_resolved', 'data' => ['category' => 'Verbos', 'clue' => 'Pregunta difícil', 'answered' => $answered]]);
        }
        GameEvent::factory()->create(['event' => 'clue_resolved', 'data' => ['category' => 'Verbos', 'clue' => 'Vista una vez', 'answered' => false]]);

        $this->actingAs($this->admin())->get('/admin/insights')
            ->assertSee('Pregunta difícil')
            ->assertDontSee('Vista una vez');
    }

    public function test_the_funnel_follows_the_teachers_who_signed_up(): void
    {
        $played = User::factory()->create();
        TeacherGame::factory()->create(['user_id' => $played->id]);
        GameEvent::factory()->count(2)->create(['user_id' => $played->id]);
        User::factory()->create();

        $steps = collect((new Insights(now()->subDays(30)))->funnel())->pluck('count', 'label');

        $this->assertSame(2, $steps['Signed up']);
        $this->assertSame(1, $steps['Published one']);
        $this->assertSame(1, $steps['Played twice or more']);
        $this->assertSame(0, $steps['Bought credits']);
    }

    public function test_publishing_records_how_much_of_the_ai_draft_was_kept(): void
    {
        $board = fn (string $second): string => json_encode(['games' => ['jeopardy' => ['categories' => [[
            'name' => 'Verbos',
            'clues' => [['prompt' => 'uno', 'answer' => 'one'], ['prompt' => $second, 'answer' => 'two']],
        ]]]]]);
        $game = TeacherGame::factory()->draft()->create(['payload' => $board('dos')]);
        GameEvent::record('ai_draft_written', $game, ['prints' => $game->writingPrints()]);
        $game->update(['payload' => $board('cambiado')]);

        $this->actingAs($game->user)->post("/my-games/{$game->id}/publish")->assertRedirect('/my-games');

        $this->assertSame(
            ['written' => 2, 'kept' => 1],
            GameEvent::where('event', 'ai_draft_published')->sole()->data,
        );
    }
}
