<?php

namespace App\Jobs;

use App\Ai\Builder;
use App\Ai\Writer;
use App\Models\TeacherGame;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * One "Ask AI" change to a game a teacher is working on.
 *
 * The smaller half of WriteGame, and the one the AI budget exists for: a
 * teacher generates once and then tunes — three words too hard, a clue that
 * gives itself away — until the game suits their class.
 *
 * Only what was asked about comes back from the model, merged in by the
 * builder's own gameEdits.js. Everything else is the teacher's own copy
 * carried over, so an edit cannot quietly reword the twenty-seven words they
 * were happy with.
 */
class EditGame implements ShouldQueue
{
    use Queueable;

    /** Shorter than a whole generation: this is one change, not thirty words. */
    public int $timeout = 240;

    /** Once, like WriteGame — a retry would charge the credit twice for one press. */
    public int $tries = 1;

    /**
     * How many exchanges travel with the next request.
     *
     * "I still don't like it, change it again" means nothing on its own: by
     * the time it is sent, the word being complained about is already out of
     * the bank. A short window of what was asked and what came back gives it
     * something to point at.
     *
     * Bounded on purpose. A chat would re-send every previous state of the
     * game and grow without limit; three one-line notes cost about a twentieth
     * of what the bank itself does, however many rounds the teacher goes. That
     * is the difference between a budget that buys ten tweaks and one that
     * buys three.
     */
    public const HISTORY_TURNS = 3;

    public function __construct(public string $gameId, public string $request) {}

    public function handle(Writer $writer, Builder $builder): void
    {
        $game = TeacherGame::find($this->gameId);
        if ($game === null) {
            return;
        }

        $unit = $game->creditUnit;

        try {
            if (! Writer::withinDailyLimit()) {
                throw new RuntimeException('Writing is paused for today. Nothing has been charged — please try again tomorrow.');
            }

            if ($unit !== null && $unit->aiCentsLeft() <= 0) {
                throw new RuntimeException('This credit has used all of its AI. You can still change the game yourself.');
            }

            $written = $writer->write(
                $builder->editPrompt($game, $this->request, self::historyOf($game->id)),
            );

            // Charged whatever happens next: the model has been paid for.
            $unit?->chargeAi($written->cents);

            $result = $builder->merge($game, $written->data);
            $game->setData($result['payload']);

            // The merge drops the bingo cards, because they were dealt from
            // the words that just changed. A published game needs them back
            // at once — a teacher can print it today.
            //
            // Best-effort: an edit that takes the bank below what a card needs
            // cannot be dealt, and that is worth a warning in the checks panel
            // rather than throwing away a change the teacher asked for and has
            // already paid the model to make.
            if ($game->isPublished()) {
                try {
                    $game->dealCardsNow();
                } catch (Throwable $e) {
                    report($e);
                }
            }

            // What the model said it did, kept for the next request to lean on
            // — and shown to the teacher, because it is the only thing that
            // explains a change that looks like nothing happened.
            self::remember($game->id, $this->request, (string) ($result['note'] ?? ''));

            $this->finish($game->id, 'ready', (string) ($result['note'] ?? ''), $result);
        } catch (Throwable $e) {
            $this->finish($game->id, 'failed', $this->explain($e));

            throw $e;
        }
    }

    /**
     * What the teacher is told.
     *
     * Ours to own: a model's error text is for a log, not for someone who
     * asked for a word to be simpler.
     */
    private function explain(Throwable $e): string
    {
        $message = $e->getMessage();

        return str_contains($message, 'paused for today') || str_contains($message, 'all of its AI')
            ? $message
            : 'We could not make that change. Nothing extra has been charged — please try again.';
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function finish(string $gameId, string $status, ?string $message = null, ?array $result = null): void
    {
        Cache::put(self::statusKey($gameId), [
            'status' => $status,
            'message' => $message,
            'changed' => $result['changed'] ?? [],
            'renamed' => $result['renamed'] ?? [],
            'removed' => $result['removed'] ?? 0,
            'final_changed' => $result['finalChanged'] ?? false,
        ], now()->addHour());
    }

    public static function statusKey(string $gameId): string
    {
        return "game-edit:{$gameId}";
    }

    /**
     * Where an edit is up to: working, ready with what moved, or failed.
     *
     * @return array<string, mixed>
     */
    public static function statusOf(string $gameId): array
    {
        return (array) Cache::get(self::statusKey($gameId), ['status' => 'idle', 'message' => null]);
    }

    public static function markWorking(string $gameId): void
    {
        Cache::put(self::statusKey($gameId), ['status' => 'working', 'message' => null], now()->addHour());
    }

    private static function historyKey(string $gameId): string
    {
        return "game-edit-history:{$gameId}";
    }

    /**
     * @return list<array{request: string, note: string}>
     */
    public static function historyOf(string $gameId): array
    {
        return array_values((array) Cache::get(self::historyKey($gameId), []));
    }

    public static function remember(string $gameId, string $request, string $note): void
    {
        $turns = self::historyOf($gameId);
        $turns[] = ['request' => $request, 'note' => $note];

        Cache::put(
            self::historyKey($gameId),
            array_slice($turns, -self::HISTORY_TURNS),
            // Long enough for one sitting with the editor. A follow-up the
            // next morning has no "it" to point at anyway, and a stale window
            // would have the model avoiding words for reasons nobody recalls.
            now()->addHours(6),
        );
    }

    /** A new bank or board describes nothing the old notes refer to. */
    public static function forget(string $gameId): void
    {
        Cache::forget(self::historyKey($gameId));
    }
}
