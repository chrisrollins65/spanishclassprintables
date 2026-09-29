<?php

namespace App\Jobs;

use App\Models\TeacherGame;
use App\Support\PrintablePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Renders one set of a teacher's bingo cards to a PDF.
 *
 * Queued because the renderer is a whole browser: it wants a few hundred MB
 * for the seconds it runs, and the droplet hosts two other sites. One worker
 * runs, so two teachers printing at once wait their turn rather than meeting
 * in memory (see the supervisor program in the server repo).
 */
class RenderBingoCards implements ShouldQueue
{
    use Queueable;

    /** A render that takes this long has hung; Chrome never needs it. */
    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        public string $gameId,
        public int $size,
        public string $stamp,
    ) {}

    public function handle(): void
    {
        $game = TeacherGame::find($this->gameId);
        if ($game === null) {
            return;
        }

        $path = self::pathFor($this->gameId, $this->size, $this->stamp);
        if (Storage::disk('local')->exists($path)) {
            return;
        }

        $html = View::make('games.cards', self::pageData($game, $this->size))->render();

        Storage::disk('local')->put($path, PrintablePdf::render($html));
    }

    /**
     * The cards of one size, six to a sheet, plus what the page prints around
     * them.
     *
     * @return array<string, mixed>
     */
    public static function pageData(TeacherGame $game, int $size): array
    {
        $payload = json_decode($game->payload, true) ?: [];
        $cards = array_values(array_filter(
            $payload['games']['bingo']['cards'] ?? [],
            fn (array $card): bool => (int) ($card['size'] ?? 0) === $size,
        ));

        return [
            'title' => $payload['games']['bingo']['title'] ?? $game->theme,
            'size' => $size,
            'sheets' => array_chunk($cards, 6),
            // The site's front door, never a room code: a card gets cut up and
            // taken home, and anyone who finds one still needs a code from a
            // teacher to reach anything.
            'siteHome' => preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')),
        ];
    }

    /** Where a rendered set lives. The stamp is what the cards were when it was made. */
    public static function pathFor(string $gameId, int $size, string $stamp): string
    {
        return "teacher-games/{$gameId}/cards-{$size}x{$size}-{$stamp}.pdf";
    }
}
