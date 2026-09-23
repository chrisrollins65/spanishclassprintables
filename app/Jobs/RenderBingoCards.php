<?php

namespace App\Jobs;

use App\Models\TeacherGame;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

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

        $pdf = Browsershot::html($html)
            ->format('Letter')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->timeout(90);

        if ($chrome = config('browsershot.chrome_path')) {
            $pdf->setChromePath($chrome);
        }
        if ($node = config('browsershot.node_binary')) {
            $pdf->setNodeBinary($node);
        }
        if ($npm = config('browsershot.npm_binary')) {
            $pdf->setNpmBinary($npm);
        }
        if (config('browsershot.no_sandbox')) {
            // The droplet runs this as the web user, not root, but Chrome's
            // sandbox still needs kernel namespaces a small VPS may not grant.
            $pdf->noSandbox();
        }

        Storage::disk('local')->put($path, $pdf->pdf());
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
