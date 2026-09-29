<?php

namespace Tests\Feature;

use App\Ai\Builder;
use App\Ai\Writer;
use App\Ai\Written;
use App\Jobs\EditGame;
use App\Jobs\RenderAnswerSheet;
use App\Jobs\WriteGame;
use App\Models\CreditEntry;
use App\Models\CreditUnit;
use App\Models\Room;
use App\Models\TeacherGame;
use App\Models\User;
use App\Support\PrintablePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Ask AI" on a game a teacher already has: the repeated half of writing one,
 * and what the per-credit AI budget is for. See docs/teacher-games.md.
 */
class GameEditTest extends TestCase
{
    use RefreshDatabase;

    private function bingoGame(?User $user = null): TeacherGame
    {
        $user ??= User::factory()->create();

        /* The first two are the ones the tests talk about; the rest are only
         * here to fill a card.
         *
         * Exactly nine, for a 3x3 card: with ten the dealer picks nine of
         * them, and whether a particular word lands on a particular card
         * depends on the draw — which made "the new word is on the cards"
         * pass alone and fail in the suite.
         */
        $items = [
            ['face' => 'queso', 'article' => 'el', 'en' => 'the cheese', 'sentence' => 'El ___ es amarillo.', 'definition' => 'Se hace con leche.'],
            ['face' => 'pan', 'article' => 'el', 'en' => 'the bread', 'sentence' => 'El ___ es rico.', 'definition' => 'Se come.'],
        ];
        foreach (['agua', 'leche', 'carne', 'arroz', 'huevo', 'sopa', 'fruta'] as $word) {
            $items[] = ['face' => $word, 'article' => 'el', 'en' => "the {$word}", 'sentence' => 'El ___ está aquí.', 'definition' => 'Se come.'];
        }

        $game = TeacherGame::factory()->create([
            'user_id' => $user->id,
            'kind' => 'bingo',
            'games' => 'bingo',
            'theme' => 'La Comida',
            'payload' => json_encode([
                'theme' => 'La Comida',
                'items' => $items,
                'games' => ['bingo' => [
                    'title' => 'La Comida',
                    'cardSets' => [['size' => 3, 'count' => 2]],
                    'cards' => [['id' => 1, 'size' => 3, 'grid' => [['el queso', 'el pan', 'el agua'], ['la leche', 'la carne', 'el arroz'], ['el huevo', 'la sopa', 'la fruta']]]],
                ]],
            ]),
        ]);

        $user->grantCredits(1, CreditEntry::PURCHASE, 'txn_'.uniqid());
        CreditUnit::claimFor($user, $game);

        return $game->fresh();
    }

    public function test_asking_for_a_change_queues_the_job(): void
    {
        Queue::fake();
        $game = $this->bingoGame();

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/ask", ['request' => 'number 1 is too hard'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'working');

        Queue::assertPushed(EditGame::class, fn (EditGame $job): bool => $job->gameId === $game->id
            && $job->request === 'number 1 is too hard');
    }

    public function test_a_second_press_joins_the_change_already_running(): void
    {
        Queue::fake();
        $game = $this->bingoGame();

        $this->actingAs($game->user)->postJson("/my-games/{$game->id}/ask", ['request' => 'make it simpler']);
        $this->actingAs($game->user)->postJson("/my-games/{$game->id}/ask", ['request' => 'make it simpler'])
            ->assertOk()
            ->assertJsonPath('status', 'working');

        // Two at once would each be written against the game as it was before
        // the other, and the second to land would throw the first away.
        Queue::assertPushed(EditGame::class, 1);
    }

    public function test_an_empty_request_is_refused(): void
    {
        Queue::fake();
        $game = $this->bingoGame();

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/ask", ['request' => ''])
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_another_teacher_cannot_ask(): void
    {
        Queue::fake();
        $game = $this->bingoGame();

        $this->actingAs(User::factory()->create())
            ->postJson("/my-games/{$game->id}/ask", ['request' => 'change everything'])
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_a_locked_game_cannot_be_changed(): void
    {
        Queue::fake();
        $game = $this->bingoGame();
        $game->lock('Refund of txn_1');

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/ask", ['request' => 'make it simpler'])
            ->assertForbidden();
    }

    public function test_a_credit_with_no_ai_left_is_told_so_and_charged_nothing(): void
    {
        Queue::fake();
        $game = $this->bingoGame();
        $game->creditUnit->chargeAi((int) config('ai.cents_per_credit') + 10);

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/ask", ['request' => 'make it simpler'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'failed');

        Queue::assertNothingPushed();
    }

    /**
     * The point of the whole design: only what was asked about comes back, so
     * the words the teacher was happy with are their own copy carried over,
     * never retyped by a model.
     */
    public function test_the_job_changes_only_what_was_asked_about(): void
    {
        $game = $this->bingoGame();

        $this->mock(Writer::class, function ($mock) {
            $mock->shouldReceive('write')->once()->andReturn(new Written([
                'changes' => [[
                    'n' => 1, 'face' => 'mantequilla', 'article' => 'la', 'en' => 'the butter',
                    'sentence' => 'La ___ es amarilla.', 'definition' => 'Se pone en el pan.',
                ]],
                'note' => "Changed #1 from 'el queso' to 'la mantequilla'.",
            ], 'gemini', 4));
        });

        (new EditGame($game->id, 'number 1 is too hard'))->handle(app(Writer::class), app(Builder::class));

        $items = $game->fresh()->data()['items'];

        $this->assertSame('mantequilla', $items[0]['face']);
        $this->assertSame('la', $items[0]['article']);

        // Untouched, to the letter.
        $this->assertSame('pan', $items[1]['face']);
        $this->assertSame('El ___ es rico.', $items[1]['sentence']);

        // The cards were dealt from the old words. This game is published, so
        // they are dealt again at once rather than left empty for the next
        // teacher who presses print.
        $cards = $game->fresh()->data()['games']['bingo']['cards'] ?? [];
        $this->assertNotEmpty($cards);
        $faces = collect($cards)->flatMap(fn (array $card): array => collect($card['grid'])->flatten()->all())->unique();
        $this->assertTrue($faces->contains('la mantequilla'), 'the new word should be on the cards');
        $this->assertFalse($faces->contains('el queso'), 'the old word should be gone from the cards');

        $status = EditGame::statusOf($game->id);
        $this->assertSame('ready', $status['status']);
        $this->assertSame([0], $status['changed']);
    }

    public function test_the_credit_is_charged_what_the_model_cost(): void
    {
        $game = $this->bingoGame();

        $this->mock(Writer::class, function ($mock) {
            $mock->shouldReceive('write')->once()->andReturn(new Written(['note' => 'Nothing to do.'], 'gemini', 7));
        });

        (new EditGame($game->id, 'leave it alone'))->handle(app(Writer::class), app(Builder::class));

        $this->assertSame(7, $game->creditUnit->fresh()->ai_cents_used);
    }

    /**
     * "I still don't like it, change it again" has no meaning on its own: by
     * then the word being complained about is out of the bank. A short window
     * of what was asked and what was done gives it something to point at.
     */
    public function test_what_was_asked_before_travels_with_the_next_request(): void
    {
        $game = $this->bingoGame();
        EditGame::remember($game->id, 'change queso', "Changed #1 from 'el queso' to 'la mantequilla'.");

        $seen = null;
        $this->mock(Writer::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('write')->once()
                ->andReturnUsing(function (string $prompt) use (&$seen) {
                    $seen = $prompt;

                    return new Written(['note' => 'Changed it again.'], 'gemini', 3);
                });
        });

        (new EditGame($game->id, 'I still do not like it, change it again'))
            ->handle(app(Writer::class), app(Builder::class));

        $this->assertStringContainsString('EARLIER IN THIS EDITING SESSION', (string) $seen);
        $this->assertStringContainsString('change queso', (string) $seen);
        // The word already turned down, so it is not handed back.
        $this->assertStringContainsString('el queso', (string) $seen);
    }

    public function test_the_window_keeps_only_the_last_few_turns(): void
    {
        $game = $this->bingoGame();

        foreach (range(1, 6) as $n) {
            EditGame::remember($game->id, "request {$n}", "did {$n}");
        }

        $turns = EditGame::historyOf($game->id);

        $this->assertCount(EditGame::HISTORY_TURNS, $turns);
        $this->assertSame('request 6', end($turns)['request']);
    }

    public function test_writing_a_whole_new_game_forgets_the_old_conversation(): void
    {
        $game = $this->bingoGame();
        // WriteGame only writes a draft — a published game is already the
        // thing the teacher paid for.
        $game->forceFill(['published_at' => null])->save();
        EditGame::remember($game->id, 'change queso', 'Changed #1.');

        $this->mock(Writer::class, function ($mock) {
            $mock->shouldReceive('write')->once()->andReturn(new Written([
                'items' => [['face' => 'leche', 'article' => 'la', 'en' => 'the milk']],
            ], 'gemini', 9));
        });

        (new WriteGame($game->id))->handle(app(Writer::class), app(Builder::class));

        // Those notes describe words that no longer exist.
        $this->assertSame([], EditGame::historyOf($game->id));
    }

    public function test_a_failed_change_tells_the_teacher_nothing_technical(): void
    {
        $game = $this->bingoGame();

        $this->mock(Writer::class, function ($mock) {
            $mock->shouldReceive('write')->once()->andThrow(new \RuntimeException('cURL error 28: timeout'));
        });

        try {
            (new EditGame($game->id, 'make it simpler'))->handle(app(Writer::class), app(Builder::class));
        } catch (\Throwable) {
            // The job rethrows so the queue records the failure.
        }

        $status = EditGame::statusOf($game->id);

        $this->assertSame('failed', $status['status']);
        $this->assertStringNotContainsString('cURL', $status['message']);
        $this->assertStringContainsString('Nothing extra has been charged', $status['message']);
    }

    public function test_the_meter_stays_hidden_until_most_of_the_budget_is_gone(): void
    {
        $game = $this->bingoGame();

        $this->actingAs($game->user)
            ->getJson("/my-games/{$game->id}/asking")
            ->assertOk()
            ->assertJsonPath('ai_percent_used', null);

        $game->creditUnit->chargeAi((int) (config('ai.cents_per_credit') * 0.9));

        $this->actingAs($game->user)
            ->getJson("/my-games/{$game->id}/asking")
            ->assertOk()
            ->assertJsonPath('ai_percent_used', 90);
    }

    /**
     * The page carries the spend, so a teacher returning to a credit with no
     * AI left meets a closed box rather than an open one that takes their
     * request and refuses it.
     */
    public function test_the_editor_page_carries_the_ai_spend_only_once_it_matters(): void
    {
        $game = $this->bingoGame();

        $this->actingAs($game->user)->get("/my-games/{$game->id}/edit")
            ->assertOk()
            ->assertSee('data-ai-percent=""', false);

        $game->creditUnit->chargeAi((int) config('ai.cents_per_credit'));

        $this->actingAs($game->user)->get("/my-games/{$game->id}/edit")
            ->assertOk()
            ->assertSee('data-ai-percent="100"', false);
    }

    /**
     * A game made here has cards nowhere else, so they are always on offer:
     * they are half of what the credit bought.
     */
    public function test_a_game_made_here_always_offers_its_cards(): void
    {
        $game = $this->bingoGame();

        $this->assertNull($game->source_code);
        $this->assertTrue($game->cardsAreWorthPrinting());
    }

    /**
     * A claimed pack is the other way round: the teacher already has a printed
     * set, and it is right until a word changes.
     */
    public function test_a_claimed_pack_offers_cards_only_once_a_word_changes(): void
    {
        $game = $this->bingoGame();
        $payload = $game->data();

        Room::create([
            'code' => 'ABC12',
            'theme' => 'La Comida',
            'payload' => json_encode($payload),
        ]);
        $game->forceFill(['source_code' => 'ABC12'])->save();

        // Nothing changed: they already have this set on paper.
        $this->assertFalse($game->fresh()->cardsAreWorthPrinting());

        $payload['items'][0]['face'] = 'mantequilla';
        $game->setData($payload);

        $this->assertTrue($game->fresh()->cardsAreWorthPrinting());
    }

    public function test_a_word_put_back_is_not_a_change(): void
    {
        $game = $this->bingoGame();
        $payload = $game->data();

        Room::create(['code' => 'ABC13', 'theme' => 'La Comida', 'payload' => json_encode($payload)]);
        $game->forceFill(['source_code' => 'ABC13'])->save();

        $changed = $payload;
        $changed['items'][0]['face'] = 'mantequilla';
        $game->setData($changed);
        $this->assertTrue($game->fresh()->cardsAreWorthPrinting());

        // Compared against the source rather than tracked with a flag, so
        // changing a word back really does put it back.
        $game->setData($payload);
        $this->assertFalse($game->fresh()->cardsAreWorthPrinting());
    }

    public function test_a_quiz_never_offers_bingo_cards(): void
    {
        $game = TeacherGame::factory()->create(['kind' => 'jeopardy', 'games' => 'jeopardy']);

        $this->assertFalse($game->cardsAreWorthPrinting());
    }

    private function quizGame(?string $sourceCode = null): TeacherGame
    {
        return TeacherGame::factory()->create([
            'kind' => 'jeopardy',
            'games' => 'jeopardy',
            'theme' => 'La Ropa',
            'source_code' => $sourceCode,
            'payload' => json_encode([
                'theme' => 'La Ropa',
                'items' => [],
                'games' => ['jeopardy' => [
                    'title' => 'La Ropa',
                    'categories' => [
                        ['name' => 'Para los pies', 'clues' => [
                            ['value' => 100, 'prompt' => 'a', 'answer' => 'b'],
                            ['value' => 200, 'prompt' => 'c', 'answer' => 'd'],
                        ]],
                        ['name' => 'Para el frío', 'clues' => [
                            ['value' => 100, 'prompt' => 'e', 'answer' => 'f'],
                            ['value' => 200, 'prompt' => 'g', 'answer' => 'h'],
                        ]],
                    ],
                    'final' => ['category' => 'Ropa', 'prompt' => 'x ___', 'answer' => 'y'],
                ]],
            ]),
        ]);
    }

    /**
     * The quiz's equivalent of the bingo cards: the page each team writes on,
     * and the thing the final wager needs a teacher to have.
     */
    public function test_a_quiz_made_here_offers_the_team_answer_sheet(): void
    {
        $game = $this->quizGame();

        $this->assertTrue($game->answerSheetIsAvailable());

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/answer-sheet")
            ->assertStatus(202)
            ->assertJsonPath('status', 'working');
    }

    /**
     * A claimed pack came with one, and this sheet is blank — so a changed
     * board is NOT a reason to offer it, unlike the bingo cards where the
     * words are printed on the page.
     */
    public function test_a_claimed_pack_never_offers_the_answer_sheet(): void
    {
        $game = $this->quizGame('ABC14');

        $this->assertFalse($game->answerSheetIsAvailable());

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/answer-sheet")
            ->assertStatus(422);
    }

    public function test_a_bingo_game_has_no_answer_sheet(): void
    {
        $this->assertFalse($this->bingoGame()->answerSheetIsAvailable());
    }

    /** The sheet prints headings and money, so only those may cost a render. */
    public function test_rewriting_a_clue_does_not_make_a_new_sheet(): void
    {
        $game = $this->quizGame();
        $sheet = fn (): array => RenderAnswerSheet::pageData($game->fresh());

        $before = $sheet();

        $payload = $game->data();
        $payload['games']['jeopardy']['categories'][0]['clues'][0]['prompt'] = 'something else entirely';
        $game->setData($payload);

        $this->assertSame($before, $sheet());
    }

    /**
     * One page, filling the paper.
     *
     * Both halves of this were wrong when it first shipped: eight identical
     * copies, because the job decided how many a class needs instead of
     * leaving that to the teacher and their copier, and a grid that sat in the
     * top half of the sheet, because `height: 100%` has nothing to resolve
     * against when neither html nor body has a height of its own.
     */
    public function test_the_sheet_is_one_page_that_fills_the_paper(): void
    {
        $game = $this->quizGame();
        $html = view('games.answer-sheet', RenderAnswerSheet::pageData($game))->render();

        $this->assertSame(1, substr_count($html, 'class="sheet"'));
        $this->assertStringContainsString('size: Letter landscape', $html);
        // A definite height, for the flex column to hand on to the grid.
        $this->assertStringContainsString('height: 8.5in', $html);
        $this->assertStringContainsString('html, body { height: 100%; }', $html);
    }

    /**
     * A rendered PDF is cached under a name built from the game's data, so a
     * change to the PAGE has to move that name too — otherwise the file on
     * disk still matches the data, is served forever, and the fix never
     * reaches a teacher who already downloaded one. That is exactly what
     * happened when this sheet was fixed the first time.
     *
     * @dataProvider printableViews
     */
    #[DataProvider('printableViews')]
    public function test_redrawing_a_printable_makes_a_new_file(string $view): void
    {
        $file = View::getFinder()->find($view);
        $before = PrintablePdf::templateStamp($view);
        $original = file_get_contents($file);

        try {
            file_put_contents($file, $original."\n{{-- a change to the design --}}\n");
            clearstatcache(true, $file);

            $this->assertNotSame($before, PrintablePdf::templateStamp($view));
        } finally {
            file_put_contents($file, $original);
            clearstatcache(true, $file);
        }

        $this->assertSame($before, PrintablePdf::templateStamp($view));
    }

    /** @return array<string, array{string}> */
    public static function printableViews(): array
    {
        return [
            'answer sheet' => ['games.answer-sheet'],
            'bingo cards' => ['games.cards'],
        ];
    }

    /** Nothing on the page may give an answer away — it is blank by design. */
    public function test_the_sheet_carries_no_clue_and_no_final_category(): void
    {
        $game = $this->quizGame();
        $html = view('games.answer-sheet', RenderAnswerSheet::pageData($game))->render();

        $this->assertStringContainsString('Para los pies', $html);
        $this->assertStringContainsString('$100', $html);
        $this->assertStringContainsString('La Apuesta Final', $html);

        // No clue text and no answer.
        $this->assertStringNotContainsString('x ___', $html);

        // And not the final's category either: it is announced only when the
        // board is empty, and a team reading it off their own sheet all game
        // has had the one thing the bet is meant to turn on.
        $this->assertStringContainsString('Categoría: <span class="rule wide"></span>', $html);
    }

    public function test_the_usage_command_moves_the_spend_without_a_model(): void
    {
        $game = $this->bingoGame();

        $this->artisan('ai:usage', ['game' => $game->id, '--percent' => 80])
            ->assertSuccessful();

        $this->assertSame(80, $game->creditUnit->fresh()->aiPercentUsed());

        $this->artisan('ai:usage', ['game' => $game->id, '--reset' => true])->assertSuccessful();

        $this->assertSame(0, $game->creditUnit->fresh()->ai_cents_used);
    }

    public function test_the_usage_command_refuses_to_run_in_production(): void
    {
        $game = $this->bingoGame();
        app()->detectEnvironment(fn (): string => 'production');

        $this->artisan('ai:usage', ['game' => $game->id, '--percent' => 80])->assertFailed();

        // It rewrites what a teacher has been charged; nothing moved.
        $this->assertSame(0, $game->creditUnit->fresh()->ai_cents_used);
    }

    public function test_the_editor_page_offers_the_box(): void
    {
        $game = $this->bingoGame();

        $this->actingAs($game->user)
            ->get("/my-games/{$game->id}/edit")
            ->assertOk()
            ->assertSee('data-ask-url="'.route('my-games.ask', $game).'"', false)
            ->assertSee('data-asking-url="'.route('my-games.asking', $game).'"', false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
