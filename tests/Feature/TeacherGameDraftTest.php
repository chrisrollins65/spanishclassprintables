<?php

namespace Tests\Feature;

use App\Jobs\WriteGame;
use App\Models\CreditEntry;
use App\Models\CreditUnit;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Making a game: the credit it costs, the draft it starts as, and what
 * publishing turns on. See docs/teacher-games.md, Phase 2.
 */
class TeacherGameDraftTest extends TestCase
{
    use RefreshDatabase;

    private function teacherWithCredits(int $credits = 1): User
    {
        $user = User::factory()->create();
        $user->grantCredits($credits, CreditEntry::PURCHASE, 'txn_'.uniqid());

        return $user;
    }

    public function test_starting_a_game_takes_a_credit_and_opens_the_editor(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits();

        $this->actingAs($user)->post('/my-games', [
            'kind' => 'bingo',
            'theme' => 'Los animales de la granja',
            'written_by' => 'me',
        ])->assertRedirect();

        $game = $user->teacherGames()->sole();

        $this->assertSame('bingo', $game->kind);
        $this->assertFalse($game->isPublished());
        $this->assertSame(0, $user->availableCredits());
        $this->assertSame(1, $user->heldCredits());

        // Written by hand: no model was asked, and nothing was queued.
        Queue::assertNothingPushed();
    }

    public function test_asking_ai_to_write_it_queues_the_job(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits();

        $this->actingAs($user)->post('/my-games', [
            'kind' => 'bingo',
            'theme' => 'Los animales de la granja',
            'written_by' => 'ai',
        ])->assertRedirect();

        $game = $user->teacherGames()->sole();

        Queue::assertPushed(WriteGame::class, fn (WriteGame $job): bool => $job->gameId === $game->id);

        // The editor waits rather than showing an empty game.
        $this->assertSame('working', WriteGame::statusOf($game->id)['status']);
    }

    /**
     * The double-tap that cost a credit: the same press arriving twice must
     * make one game, not two. The button disables itself, but that is gone on
     * a refresh, a back button or a second tab — so the server refuses too.
     */
    public function test_pressing_start_twice_makes_one_game(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits(2);
        $press = ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me'];

        $first = $this->actingAs($user)->post('/my-games', $press);
        $second = $this->actingAs($user)->post('/my-games', $press);

        $this->assertSame(1, $user->teacherGames()->count());

        // The second press lands on the game the first one made, not an error.
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));

        // And only one credit moved.
        $this->assertSame(1, $user->availableCredits());
    }

    public function test_a_repeat_press_does_not_write_the_game_twice(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits(2);
        $press = ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'ai'];

        $this->actingAs($user)->post('/my-games', $press);
        $this->actingAs($user)->post('/my-games', $press);

        // Two model calls for one press is the expensive half of this bug.
        Queue::assertPushed(WriteGame::class, 1);
    }

    /**
     * The repeat check reads before it writes, so it only means something if
     * two simultaneous presses are serialised. A double-tap on a phone is two
     * requests at once: before the lock, eight parallel presses made two
     * games and took two credits, while a sequential test passed.
     */
    public function test_a_press_is_handled_under_a_lock_of_its_own(): void
    {
        $user = $this->teacherWithCredits(2);

        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()
            ->andReturnUsing(fn (int $seconds, callable $work) => $work());

        Cache::shouldReceive('lock')->once()
            ->withArgs(fn (string $key): bool => str_starts_with($key, "start-game:{$user->id}:bingo:"))
            ->andReturn($lock);

        $this->actingAs($user)->post('/my-games', [
            'kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me',
        ])->assertRedirect();

        $this->assertSame(1, $user->teacherGames()->count());
    }

    public function test_a_press_stuck_behind_another_charges_nothing(): void
    {
        $user = $this->teacherWithCredits();

        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException);
        Cache::shouldReceive('lock')->once()->andReturn($lock);

        $this->actingAs($user)->post('/my-games', [
            'kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me',
        ])->assertRedirect(route('my-games'));

        $this->assertSame(0, $user->teacherGames()->count());
        $this->assertSame(1, $user->availableCredits());
    }

    public function test_a_different_game_on_the_same_topic_is_still_its_own_game(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits(2);

        $this->actingAs($user)->post('/my-games', ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me']);
        $this->actingAs($user)->post('/my-games', ['kind' => 'jeopardy', 'theme' => 'Los animales', 'written_by' => 'me']);

        // Same topic, different game: a teacher making the bingo and the quiz
        // of one unit is doing exactly what they meant to.
        $this->assertSame(2, $user->teacherGames()->count());
        $this->assertSame(0, $user->availableCredits());
    }

    public function test_the_same_topic_again_later_is_a_second_game(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits(2);
        $press = ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me'];

        $this->actingAs($user)->post('/my-games', $press);

        // Past the window, this is a teacher asking for another one.
        $this->travel(31)->seconds();
        $this->actingAs($user)->post('/my-games', $press);

        $this->assertSame(2, $user->teacherGames()->count());
    }

    public function test_a_published_game_does_not_swallow_a_new_one_on_the_same_topic(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits(2);
        $press = ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me'];

        $this->actingAs($user)->post('/my-games', $press);
        $user->teacherGames()->sole()->forceFill(['published_at' => now()])->save();

        $this->actingAs($user)->post('/my-games', $press);

        $this->assertSame(2, $user->teacherGames()->count());
    }

    public function test_the_form_that_spends_a_credit_guards_itself_in_the_browser_too(): void
    {
        $user = $this->teacherWithCredits();

        $this->actingAs($user)->get('/my-games/new')
            ->assertOk()
            ->assertSee('data-once', false)
            ->assertSee('/site/once.js', false);
    }

    public function test_a_game_cannot_be_started_without_a_credit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/my-games', [
            'kind' => 'bingo',
            'theme' => 'Los animales de la granja',
            'written_by' => 'me',
        ])->assertRedirect(route('credits'));

        // Nothing taken, and no unpaid draft left behind.
        $this->assertSame(0, $user->teacherGames()->withTrashed()->count());
    }

    /**
     * The one the walkthrough caught: the editor fetches payload.json, so
     * gating that on publication left every new game with an editor that
     * could not load the game it was editing.
     */
    public function test_a_draft_serves_its_payload_to_its_own_editor(): void
    {
        $game = TeacherGame::factory()->draft()->create();

        $this->actingAs($game->user)
            ->get("/my-games/{$game->id}/edit")
            ->assertOk()
            ->assertSee('data-payload-url="'.route('my-games.payload', $game).'"', false);

        $this->actingAs($game->user)
            ->get("/my-games/{$game->id}/payload.json")
            ->assertOk()
            ->assertJsonPath('theme', 'Los Deportes');
    }

    public function test_a_draft_is_not_playable_or_printable(): void
    {
        $game = TeacherGame::factory()->draft()->create();

        $this->actingAs($game->user)->get("/my-games/{$game->id}/play")->assertForbidden();
        $this->actingAs($game->user)->postJson("/my-games/{$game->id}/cards", ['size' => 4])->assertForbidden();
    }

    public function test_another_teacher_cannot_read_a_draft(): void
    {
        $game = TeacherGame::factory()->draft()->create();

        $this->actingAs(User::factory()->create())
            ->get("/my-games/{$game->id}/payload.json")
            ->assertNotFound();
    }

    public function test_an_unfinished_game_says_what_is_missing_instead_of_publishing(): void
    {
        $game = TeacherGame::factory()->draft()->create([
            'kind' => 'jeopardy',
            'payload' => json_encode(TeacherGame::blank('jeopardy', 'Los Deportes')),
        ]);

        $this->actingAs($game->user)
            ->from(route('my-games.edit', $game))
            ->post("/my-games/{$game->id}/publish")
            ->assertRedirect(route('my-games.edit', $game))
            ->assertSessionHasErrors('publish');

        $this->assertFalse($game->fresh()->isPublished());
    }

    public function test_publishing_makes_it_playable(): void
    {
        $game = TeacherGame::factory()->draft()->create([
            'kind' => 'jeopardy',
            'payload' => json_encode([
                'theme' => 'Los Deportes',
                'items' => [],
                'games' => ['jeopardy' => ['title' => 'Los Deportes', 'categories' => [
                    ['name' => 'Deportes', 'clues' => [
                        ['value' => 100, 'prompt' => 'Se juega con los pies.', 'answer' => 'el fútbol'],
                    ]],
                ]]],
            ]),
        ]);

        $this->actingAs($game->user)
            ->post("/my-games/{$game->id}/publish")
            ->assertRedirect(route('my-games'));

        $this->assertTrue($game->fresh()->isPublished());
        $this->actingAs($game->user)->get("/my-games/{$game->id}/play")->assertOk();
    }

    public function test_a_published_game_cannot_be_published_again(): void
    {
        $game = TeacherGame::factory()->create();

        $this->actingAs($game->user)->post("/my-games/{$game->id}/publish")->assertForbidden();
    }

    public function test_deleting_a_draft_gives_the_credit_back(): void
    {
        $user = $this->teacherWithCredits();
        $game = TeacherGame::factory()->draft()->create(['user_id' => $user->id]);
        CreditUnit::claimFor($user, $game);

        $this->assertSame(0, $user->availableCredits());

        $this->actingAs($user)->delete("/my-games/{$game->id}")->assertRedirect(route('my-games'));

        $this->assertSame(1, $user->availableCredits());
        $this->assertSoftDeleted($game);
    }

    public function test_deleting_a_published_game_keeps_the_credit_spent(): void
    {
        $user = $this->teacherWithCredits();
        $game = TeacherGame::factory()->create(['user_id' => $user->id]);
        CreditUnit::claimFor($user, $game);

        $this->actingAs($user)->delete("/my-games/{$game->id}")->assertRedirect(route('my-games'));

        // They have had the thing they bought.
        $this->assertSame(0, $user->availableCredits());
    }
}
