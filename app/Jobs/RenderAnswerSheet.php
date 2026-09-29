<?php

namespace App\Jobs;

use App\Models\TeacherGame;
use App\Support\PrintablePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * The team answer sheet for a teacher's own quiz: one page per team, written
 * on for the whole game.
 *
 * The quiz's equivalent of the bingo cards, and offered for the same reason:
 * it is the page students physically hold. It is not a convenience either —
 * the final wager asks every team to write a bet down before the clue is
 * shown, so without this the round the whole game builds to runs on scrap
 * paper.
 *
 * Blank by design, which is what makes it safe: category headings, the money,
 * and empty boxes. Nothing on it is an answer.
 *
 * ONE page, like the sheet in the TpT pack. A teacher needs one per team and
 * runs off as many as the class wants — from the copier, or from the copies
 * box in the print dialog. Printing a fixed number of identical pages into the
 * file only decides for them, usually wrongly.
 */
class RenderAnswerSheet implements ShouldQueue
{
    use Queueable;

    /** A render that takes this long has hung; Chrome never needs it. */
    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        public string $gameId,
        public string $stamp,
    ) {}

    public function handle(): void
    {
        $game = TeacherGame::find($this->gameId);
        if ($game === null) {
            return;
        }

        $path = self::pathFor($this->gameId, $this->stamp);
        if (Storage::disk('local')->exists($path)) {
            return;
        }

        $html = View::make('games.answer-sheet', self::pageData($game))->render();

        Storage::disk('local')->put($path, PrintablePdf::render($html));
    }

    /**
     * The grid, and nothing that could give a clue away.
     *
     * Rows are the money in the order the board has them, so a team's sheet
     * reads down the same way the screen does and a child looking for "the
     * three hundred one" finds it in the row they expect.
     *
     * @return array<string, mixed>
     */
    public static function pageData(TeacherGame $game): array
    {
        $payload = json_decode((string) $game->payload, true) ?: [];
        $board = $payload['games']['jeopardy'] ?? [];
        $categories = array_values(array_filter($board['categories'] ?? [], 'is_array'));

        $depth = max(0, ...array_map(
            fn (array $category): int => count($category['clues'] ?? []),
            $categories ?: [[]],
        ));

        $rows = [];
        foreach (range(0, max(0, $depth - 1)) as $row) {
            $rows[] = array_map(function (array $category) use ($row): ?string {
                $clue = ($category['clues'] ?? [])[$row] ?? null;

                return is_array($clue) && isset($clue['value']) ? '$'.$clue['value'] : null;
            }, $categories);
        }

        return [
            'title' => (string) ($board['title'] ?? $game->theme ?? ''),
            'categories' => array_map(fn (array $c): string => (string) ($c['name'] ?? ''), $categories),
            'rows' => $depth > 0 ? $rows : [],
            // The final's own category is NOT passed: it is announced when the
            // board is empty, and a team reading it off their sheet all game
            // has had the thing the bet turns on.
            'hasFinal' => ! empty($board['final']['prompt']),
        ];
    }

    /** Where a rendered sheet lives. The stamp is what the board was when it was made. */
    public static function pathFor(string $gameId, string $stamp): string
    {
        return "teacher-games/{$gameId}/answer-sheet-{$stamp}.pdf";
    }
}
