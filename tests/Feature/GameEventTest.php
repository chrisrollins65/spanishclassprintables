<?php

namespace Tests\Feature;

use App\Models\GameEvent;
use App\Models\Room;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * What the game screens report (public/game/analytics.js). The endpoint is open
 * to anyone, so most of this is about what it refuses to keep.
 */
class GameEventTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        return Room::create(['code' => 'ABCD', 'theme' => 'La familia', 'payload' => '{}']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function report(array $overrides = []): void
    {
        $this->postJson('/api/game-events', $overrides + [
            'play_id' => 'play-1',
            'room_code' => 'ABCD',
            'events' => [['event' => 'game_started', 'game' => 'bingo', 'data' => ['mode' => 'en']]],
        ])->assertNoContent();
    }

    public function test_a_room_with_no_account_reports_anonymously(): void
    {
        $this->room();

        $this->report();

        $event = GameEvent::sole();
        $this->assertSame('game_started', $event->event);
        $this->assertSame('bingo', $event->game);
        $this->assertSame('ABCD', $event->room_code);
        $this->assertNull($event->user_id);
        $this->assertSame(['mode' => 'en'], $event->data);
    }

    public function test_a_logged_in_teacher_is_linked_to_what_they_play(): void
    {
        $this->room();
        $teacher = User::factory()->create();

        $this->actingAs($teacher);
        $this->report();

        $this->assertSame($teacher->id, GameEvent::sole()->user_id);
    }

    public function test_a_key_that_is_not_listed_never_reaches_the_table(): void
    {
        $this->room();

        $this->report(['events' => [[
            'event' => 'game_finished',
            'game' => 'jeopardy',
            'data' => ['teams' => 3, 'team_names' => 'Maria, Juan', 'winner' => 'Maria'],
        ]]]);

        $this->assertSame(['teams' => 3], GameEvent::sole()->data);
    }

    public function test_a_value_that_is_not_plain_is_dropped_and_a_long_one_is_cut(): void
    {
        $this->room();

        $this->report(['events' => [[
            'event' => 'clue_resolved',
            'data' => ['category' => ['nested' => 'x'], 'clue' => str_repeat('a', 500)],
        ]]]);

        $data = GameEvent::sole()->data;
        $this->assertArrayNotHasKey('category', $data);
        $this->assertLessThan(210, strlen($data['clue']));
    }

    public function test_an_unknown_event_is_dropped_and_the_rest_of_the_batch_is_kept(): void
    {
        $this->room();

        $this->report(['events' => [['event' => 'made_up'], ['event' => 'room_opened']]]);

        $this->assertSame('room_opened', GameEvent::sole()->event);
    }

    public function test_a_room_that_does_not_exist_records_nothing(): void
    {
        $this->report();

        $this->assertDatabaseEmpty('game_events');
    }

    public function test_a_wrong_code_is_recorded_even_though_it_has_no_room(): void
    {
        $this->report(['room_code' => null, 'events' => [['event' => 'code_not_found', 'data' => ['code' => 'WRONG']]]]);

        $this->assertSame(['code' => 'WRONG'], GameEvent::sole()->data);
    }

    public function test_a_game_can_only_be_reported_on_by_its_owner(): void
    {
        $game = TeacherGame::factory()->create();
        $report = ['room_code' => null, 'own_game' => $game->id];

        $this->report($report);
        $this->actingAs(User::factory()->create());
        $this->report($report);
        $this->assertDatabaseEmpty('game_events');

        $this->actingAs($game->user);
        $this->report($report);
        $this->assertSame($game->id, GameEvent::sole()->teacher_game_id);
    }

    public function test_our_own_plays_are_left_out(): void
    {
        $this->room();
        config(['site.admins' => ['shop@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'shop@example.com']));
        $this->report();

        $this->assertDatabaseEmpty('game_events');
    }

    public function test_a_batch_too_big_is_refused_without_an_error(): void
    {
        $this->room();

        $this->report(['events' => array_fill(0, 31, ['event' => 'room_opened'])]);

        $this->assertDatabaseEmpty('game_events');
    }

    public function test_old_events_are_pruned_on_the_schedule_the_policy_promises(): void
    {
        $old = GameEvent::factory()->create(['created_at' => now()->subMonths(GameEvent::KEEP_MONTHS)->subDay()]);
        $recent = GameEvent::factory()->create(['created_at' => now()->subMonths(GameEvent::KEEP_MONTHS)->addDay()]);

        Artisan::call('model:prune', ['--model' => [GameEvent::class]]);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_the_shell_loads_the_reporter_before_anything_it_could_catch(): void
    {
        $html = $this->get('/j/ABCD')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, '/game/ui.js'), strpos($html, '/game/analytics.js'));
    }
}
