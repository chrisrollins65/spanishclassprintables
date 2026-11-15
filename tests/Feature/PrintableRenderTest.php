<?php

namespace Tests\Feature;

use App\Jobs\RenderAnswerSheet;
use App\Jobs\RenderBingoCards;
use App\Models\TeacherGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The printables, rendered for real by headless Chrome.
 *
 * Every other test fakes the queue or reads the page data, so none of them
 * would notice Chrome failing to start, a template Chrome can't print, or a
 * layout that spills onto a second sheet. These do, which is why they need a
 * Chrome: CI points BROWSERSHOT_CHROME_PATH at the runner's own.
 *
 * Only the page count is checked, never the bytes. Fonts differ between this
 * machine, the runner and the droplet, and so does the PDF they produce.
 *
 * `php artisan test --exclude-group=chrome` skips them for a quick run.
 */
#[Group('chrome')]
class PrintableRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /** One page, however many teams: the teacher copies it (see RenderAnswerSheet). */
    public function test_the_answer_sheet_prints_as_one_page(): void
    {
        $game = TeacherGame::factory()->create([
            'kind' => 'jeopardy',
            'games' => 'jeopardy',
            'theme' => 'La Ropa',
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

        (new RenderAnswerSheet($game->id, 'test'))->handle();

        $this->assertPdfPages(1, RenderAnswerSheet::pathFor($game->id, 'test'));
    }

    /** Six cards to a sheet, so seven cards are two sheets. */
    public function test_bingo_cards_print_six_to_a_sheet(): void
    {
        $grid = [['el queso', 'el pan', 'el agua'], ['la leche', 'la carne', 'el arroz'], ['el huevo', 'la sopa', 'la fruta']];
        $cards = array_map(fn (int $id): array => ['id' => $id, 'size' => 3, 'grid' => $grid], range(1, 7));

        $game = TeacherGame::factory()->create([
            'kind' => 'bingo',
            'games' => 'bingo',
            'theme' => 'La Comida',
            'payload' => json_encode([
                'theme' => 'La Comida',
                'items' => [],
                'games' => ['bingo' => [
                    'title' => 'La Comida',
                    'cardSets' => [['size' => 3, 'count' => 7]],
                    'cards' => $cards,
                ]],
            ]),
        ]);

        (new RenderBingoCards($game->id, 3, 'test'))->handle();

        $this->assertPdfPages(2, RenderBingoCards::pathFor($game->id, 3, 'test'));
    }

    private function assertPdfPages(int $expected, string $path): void
    {
        Storage::disk('local')->assertExists($path);
        $pdf = Storage::disk('local')->get($path);

        $this->assertStringStartsWith('%PDF-', $pdf);
        // Chrome writes each page's dictionary uncompressed; `/Type /Pages`
        // is the tree above them, which the lookahead leaves out.
        $this->assertSame($expected, preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $pdf));
    }
}
