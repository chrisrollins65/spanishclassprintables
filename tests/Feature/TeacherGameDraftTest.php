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

    /**
     * The form itself makes nothing and charges nothing.
     *
     * It reads what kind of practice the description asks for and shows it,
     * because that is the one decision a teacher cannot discover any other
     * way: "the subjunctive" is as true of a board of verb forms as of a board
     * of whole sentences, and finding that out after the credit was spent is
     * what this step exists to stop.
     */
    public function test_the_form_classifies_without_making_a_game(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits();

        $this->actingAs($user)->post('/my-games', [
            'kind' => 'jeopardy',
            'theme' => 'El subjuntivo',
            'written_by' => 'ai',
        ])->assertRedirect(route('my-games.confirm'))
            ->assertSessionHas('pendingGame');

        $this->assertSame(0, $user->teacherGames()->count());
        $this->assertSame(1, $user->availableCredits());
        Queue::assertNothingPushed();
    }

    /**
     * The screen itself: what was read, and every deck this kind can play.
     * Rendering it is the point — a mistake in the view only shows at runtime.
     */
    public function test_the_confirm_screen_shows_the_choice_it_made(): void
    {
        $user = $this->teacherWithCredits();

        $this->actingAs($user)
            ->withSession(['pendingGame' => [
                'kind' => 'jeopardy',
                'theme' => 'El subjuntivo',
                'describe' => 'They keep using the indicative after "dudo que".',
                'written_by' => 'ai',
                'type' => 'forma',
                'why' => 'one mood drilled across persons',
            ]])
            ->get('/my-games/confirm')
            ->assertOk()
            ->assertSee('El subjuntivo')
            ->assertSee('one mood drilled across persons')
            // What it read, not the vocabulary default.
            ->assertSee('Verb &amp; word forms (grammar)', false)
            // The alternative the teacher could not otherwise know about.
            ->assertSee('Whole sentences (translation)');
    }

    /**
     * A bingo skips the screen and is made straight away.
     *
     * Every deck a bingo can play asks the same thing of the class — recall one
     * item from a clue — so there is no choice of activity to put to a teacher,
     * only our own distinction between kinds of bank, which this form
     * deliberately stopped asking about. The screen is for the one question a
     * teacher cannot discover otherwise, and bingo cannot ask it.
     */
    public function test_a_bingo_skips_the_confirm_screen(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits();

        $this->actingAs($user)->post('/my-games', [
            'kind' => 'bingo',
            'theme' => 'Los animales de la granja',
            'written_by' => 'me',
        ])->assertRedirect();

        // Made, not pending: the credit went and the editor is open.
        $this->assertSame(1, $user->teacherGames()->count());
        $this->assertSame(0, $user->availableCredits());
    }

    /** A bingo is never offered the quiz-only sentence deck. */
    public function test_the_confirm_screen_hides_decks_this_game_cannot_play(): void
    {
        $this->actingAs($this->teacherWithCredits())
            ->withSession(['pendingGame' => [
                'kind' => 'bingo',
                'theme' => 'Los animales',
                'describe' => '',
                'written_by' => 'me',
                'type' => 'vocabulario',
                'why' => '',
            ]])
            ->get('/my-games/confirm')
            ->assertOk()
            ->assertSee('Vocabulary words')
            ->assertDontSee('Whole sentences (translation)');
    }

    /** With nothing pending, the confirm screen sends them back to the form. */
    public function test_the_confirm_screen_needs_something_pending(): void
    {
        $this->actingAs($this->teacherWithCredits())
            ->get('/my-games/confirm')
            ->assertRedirect(route('my-games.create'));
    }

    /**
     * A deck the chosen game cannot be played as is refused whatever the form
     * says: a sentence deck is quiz-only, and a bingo built on one would have
     * no way to call a card. Unknown lands on vocabulary, as it does
     * everywhere else, rather than failing the creation.
     */
    public function test_a_bingo_cannot_be_started_on_a_quiz_only_deck(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits();

        $this->actingAs($user)->post('/my-games/confirm', [
            'kind' => 'bingo',
            'theme' => 'Los animales',
            'written_by' => 'me',
            'type' => 'frase',
        ])->assertRedirect();

        $payload = $user->teacherGames()->sole()->data();
        $this->assertArrayNotHasKey('type', $payload);
    }

    /**
     * A sentence keeps the article it starts with.
     *
     * `tidyItems` splits "la camisa" into an article and a face, which is right
     * for a word and wrong for a whole sentence: "La enfermera trabaja en el
     * hospital" came back as article "la" plus a face starting "enfermera",
     * which is not what the board answers with and matches nothing in the bank.
     * It was latent until a generated sentence happened to begin with an
     * article — every earlier one started "Si".
     */
    public function test_a_sentence_deck_keeps_the_article_on_the_sentence(): void
    {
        $game = new TeacherGame;
        $game->id = $game->newUniqueId();
        $game->user_id = $this->teacherWithCredits()->id;
        $game->fill([
            'theme' => 'Las profesiones',
            'kind' => 'jeopardy',
            'games' => 'jeopardy',
            'payload' => json_encode(TeacherGame::blank(
                'jeopardy', 'Las profesiones', 'frase', ['en'],
                ['answerIsOpen' => true, 'stripsArticles' => false],
            )),
        ]);
        $game->save();

        $game->applyWriting([
            'items' => [['face' => 'La enfermera trabaja en el hospital.', 'en' => 'The nurse works at the hospital.']],
        ], 'ai');

        $item = $game->fresh()->data()['items'][0];
        $this->assertSame('La enfermera trabaja en el hospital.', $item['face']);
        $this->assertArrayNotHasKey('article', $item);
    }

    /** The same pass still takes "la camisa" apart on a deck of words. */
    public function test_a_word_deck_still_splits_the_article_off(): void
    {
        $game = new TeacherGame;
        $game->id = $game->newUniqueId();
        $game->user_id = $this->teacherWithCredits()->id;
        $game->fill([
            'theme' => 'La ropa',
            'kind' => 'bingo',
            'games' => 'bingo',
            'payload' => json_encode(TeacherGame::blank('bingo', 'La ropa')),
        ]);
        $game->save();

        $game->applyWriting([
            'items' => [['face' => 'la camisa', 'en' => 'the shirt']],
        ], 'ai');

        $item = $game->fresh()->data()['items'][0];
        $this->assertSame('camisa', $item['face']);
        $this->assertSame('la', $item['article']);
    }

    public function test_starting_a_game_takes_a_credit_and_opens_the_editor(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits();

        $this->actingAs($user)->post('/my-games/confirm', [
            'kind' => 'bingo',
            'theme' => 'Los animales de la granja',
            'written_by' => 'me',
            'type' => 'vocabulario',
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

        $this->actingAs($user)->post('/my-games/confirm', [
            'kind' => 'bingo',
            'theme' => 'Los animales de la granja',
            'written_by' => 'ai',
            'type' => 'vocabulario',
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
        $press = ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me', 'type' => 'vocabulario'];

        $first = $this->actingAs($user)->post('/my-games/confirm', $press);
        $second = $this->actingAs($user)->post('/my-games/confirm', $press);

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
        $press = ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'ai', 'type' => 'vocabulario'];

        $this->actingAs($user)->post('/my-games/confirm', $press);
        $this->actingAs($user)->post('/my-games/confirm', $press);

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

        $this->actingAs($user)->post('/my-games/confirm', [
            'kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me',
            'type' => 'vocabulario',
        ])->assertRedirect();

        $this->assertSame(1, $user->teacherGames()->count());
    }

    public function test_a_press_stuck_behind_another_charges_nothing(): void
    {
        $user = $this->teacherWithCredits();

        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException);
        Cache::shouldReceive('lock')->once()->andReturn($lock);

        $this->actingAs($user)->post('/my-games/confirm', [
            'kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me',
            'type' => 'vocabulario',
        ])->assertRedirect(route('my-games'));

        $this->assertSame(0, $user->teacherGames()->count());
        $this->assertSame(1, $user->availableCredits());
    }

    public function test_a_different_game_on_the_same_topic_is_still_its_own_game(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits(2);

        $this->actingAs($user)->post('/my-games/confirm', ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me', 'type' => 'vocabulario']);
        $this->actingAs($user)->post('/my-games/confirm', ['kind' => 'jeopardy', 'theme' => 'Los animales', 'written_by' => 'me', 'type' => 'vocabulario']);

        // Same topic, different game: a teacher making the bingo and the quiz
        // of one unit is doing exactly what they meant to.
        $this->assertSame(2, $user->teacherGames()->count());
        $this->assertSame(0, $user->availableCredits());
    }

    public function test_the_same_topic_again_later_is_a_second_game(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits(2);
        $press = ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me', 'type' => 'vocabulario'];

        $this->actingAs($user)->post('/my-games/confirm', $press);

        // Past the window, this is a teacher asking for another one.
        $this->travel(31)->seconds();
        $this->actingAs($user)->post('/my-games/confirm', $press);

        $this->assertSame(2, $user->teacherGames()->count());
    }

    public function test_a_published_game_does_not_swallow_a_new_one_on_the_same_topic(): void
    {
        Queue::fake();
        $user = $this->teacherWithCredits(2);
        $press = ['kind' => 'bingo', 'theme' => 'Los animales', 'written_by' => 'me', 'type' => 'vocabulario'];

        $this->actingAs($user)->post('/my-games/confirm', $press);
        $user->teacherGames()->sole()->forceFill(['published_at' => now()])->save();

        $this->actingAs($user)->post('/my-games/confirm', $press);

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

        $this->actingAs($user)->post('/my-games/confirm', [
            'kind' => 'bingo',
            'theme' => 'Los animales de la granja',
            'written_by' => 'me',
            'type' => 'vocabulario',
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
