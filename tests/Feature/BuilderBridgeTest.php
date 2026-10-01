<?php

namespace Tests\Feature;

use App\Ai\Builder;
use App\Models\TeacherGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * That the packet builder's prompt machinery is actually here and runs.
 *
 * Everything AI on this site goes through node into
 * resources/scripts/builder — copied from the builder by its
 * scripts/sync-site-shared.js. Those files are generated, so they are easy to
 * leave uncommitted or to let go stale, and nothing else notices: the app
 * boots, the pages render, the tests pass, and then the first teacher to spend
 * a credit gets "We could not write this one."
 *
 * This is the cheapest possible guard against that — it builds a real prompt
 * through a real node process, so a missing file, a missing node, or a broken
 * sync fails here instead of in front of a paying teacher.
 */
class BuilderBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bridge_and_the_builder_files_are_present(): void
    {
        $this->assertFileExists(resource_path('scripts/game-ai.cjs'));

        foreach ([
            'scripts/builder/ai/prompts.js',
            'scripts/builder/shared/wordsPrompt.js',
            'scripts/builder/tptVocab.js',
            'scripts/builder/jeopardyBoard.js',
            'scripts/builder/gameEdits.js',
            // Scopes that folder back to CommonJS; without it the site's own
            // "type": "module" makes every require() in there a ReferenceError.
            'scripts/builder/package.json',
        ] as $file) {
            $this->assertFileExists(resource_path($file), "Run the builder's scripts/sync-site-shared.js.");
        }
    }

    public function test_a_bingo_prompt_is_built_through_node(): void
    {
        $game = TeacherGame::factory()->create([
            'kind' => 'bingo',
            'games' => 'bingo',
            'theme' => 'Los animales de la granja',
        ]);

        $prompt = app(Builder::class)->generatePrompt($game);

        $this->assertStringContainsString('Los animales de la granja', $prompt);
        // A rule from the builder's own ITEM_RULES, so a prompt that arrives
        // empty or half-rendered does not pass.
        $this->assertStringContainsString('article', $prompt);
        $this->assertGreaterThan(1000, strlen($prompt));
    }

    public function test_a_quiz_prompt_is_built_through_node(): void
    {
        $game = TeacherGame::factory()->create([
            'kind' => 'jeopardy',
            'games' => 'jeopardy',
            'theme' => 'La Ropa',
        ]);

        $prompt = app(Builder::class)->generatePrompt($game);

        $this->assertStringContainsString('La Ropa', $prompt);
        $this->assertGreaterThan(1000, strlen($prompt));
    }

    /**
     * The edit prompt is the one that carries the game as it stands, so it is
     * also the one that proves the bank reaches node and comes back numbered.
     */
    public function test_an_edit_prompt_carries_the_bank_and_the_history(): void
    {
        $game = TeacherGame::factory()->create([
            'kind' => 'bingo',
            'games' => 'bingo',
            'theme' => 'La Comida',
            'payload' => json_encode([
                'theme' => 'La Comida',
                'items' => [['face' => 'queso', 'article' => 'el', 'en' => 'the cheese']],
                'games' => ['bingo' => ['title' => 'La Comida']],
            ]),
        ]);

        $prompt = app(Builder::class)->editPrompt($game, 'change it again', [
            ['request' => 'change queso', 'note' => "Changed #1 from 'el queso' to 'la mantequilla'."],
        ]);

        $this->assertStringContainsString('queso', $prompt);
        $this->assertStringContainsString('change it again', $prompt);
        // Without this a follow-up has nothing to point at; see EditGame.
        $this->assertStringContainsString('EARLIER IN THIS EDITING SESSION', $prompt);
        $this->assertStringContainsString('la mantequilla', $prompt);
    }

    /** The merge is the builder's too, and decides what a teacher is left with. */
    public function test_an_edit_is_merged_by_the_builders_own_code(): void
    {
        $game = TeacherGame::factory()->create([
            'kind' => 'bingo',
            'games' => 'bingo',
            'theme' => 'La Comida',
            'payload' => json_encode([
                'theme' => 'La Comida',
                'items' => [
                    ['face' => 'queso', 'article' => 'el', 'en' => 'the cheese'],
                    ['face' => 'pan', 'article' => 'el', 'en' => 'the bread'],
                ],
                'games' => ['bingo' => ['title' => 'La Comida']],
            ]),
        ]);

        $result = app(Builder::class)->merge($game, [
            'changes' => [['n' => 1, 'face' => 'mantequilla', 'article' => 'la', 'en' => 'the butter']],
            'note' => 'Changed #1.',
        ]);

        $this->assertSame('mantequilla', $result['payload']['items'][0]['face']);
        $this->assertSame('pan', $result['payload']['items'][1]['face']);
        $this->assertSame([0], $result['changed']);
    }
}
