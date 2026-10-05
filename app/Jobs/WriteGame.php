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
 * Writes a teacher's game with AI (docs/teacher-games.md, Phase 2).
 *
 * Queued because a model takes 20-60 seconds, and a web request holding a
 * php-fpm worker that long is a request that times out.
 *
 * The rules it writes to are not written here: the prompt comes from the
 * packet builder, through App\Ai\Builder, so a teacher's game is written to
 * the same rules as the packs sold on TpT.
 */
class WriteGame implements ShouldQueue
{
    use Queueable;

    /** Long enough for a slow model and its fallback, and no longer. */
    public int $timeout = 400;

    /**
     * Once. A retry would charge the credit twice for one press, and a teacher
     * who wants another go can ask for one.
     */
    public int $tries = 1;

    public function __construct(public string $gameId) {}

    public function handle(Writer $writer, Builder $builder): void
    {
        $game = TeacherGame::find($this->gameId);
        if ($game === null || $game->isPublished()) {
            return;
        }

        $unit = $game->creditUnit;

        try {
            // Re-checked here, not only at the button: a job can sit in the
            // queue behind others while the day's spend climbs.
            if (! Writer::withinDailyLimit()) {
                throw new RuntimeException('Writing is paused for today. Nothing has been charged — please try again tomorrow.');
            }

            if ($unit !== null && $unit->aiCentsLeft() <= 0) {
                throw new RuntimeException('This credit has used all of its AI. You can still write and edit the game yourself.');
            }

            /* One call, or two for a grammar quiz.
             *
             * The builder decides how many and what each writes (Builder::plan):
             * vocabulary writes its words and its board in one reply, while a
             * grammar deck writes the bank first and the board over it, because
             * its words carry a formula the one-reply prompt has no room for.
             *
             * The replies are merged and applied ONCE at the end. Applying each
             * as it arrives would leave a game with words and no board if the
             * second call failed — and applyWriting replaces items wholesale, so
             * the second reply would wipe the first's words on its way in.
             */
            $data = [];
            $writtenBy = '';

            foreach ($builder->plan($game) as $step) {
                // Re-checked per call for the same reason it is checked at all:
                // the first call of a two-call game can exhaust the budget.
                if ($unit !== null && $unit->aiCentsLeft() <= 0) {
                    throw new RuntimeException('This credit has used all of its AI. You can still write and edit the game yourself.');
                }

                $written = $writer->write($builder->generatePrompt($game, $step, $data));

                // Charged whatever happens next: the model has been paid for, and
                // a budget that counted only successes would not be a budget.
                $unit?->chargeAi($written->cents);

                $data = array_merge($data, $written->data);
                $writtenBy = $written->writtenBy;
            }

            $game->applyWriting($data, $writtenBy);

            // A wholly new game: whatever was asked of AI before was asked of
            // words that no longer exist, so "change it again" would point at
            // nothing. See EditGame::historyOf().
            EditGame::forget($game->id);

            $this->finish($game->id, 'ready');
        } catch (Throwable $e) {
            $this->finish($game->id, 'failed', $this->explain($e));

            throw $e;
        }
    }

    /**
     * What the teacher is told.
     *
     * Ours to own: a model's error text is for a log, not for someone who
     * pressed a button and waited a minute.
     */
    private function explain(Throwable $e): string
    {
        $message = $e->getMessage();

        return str_contains($message, 'paused for today') || str_contains($message, 'all of its AI')
            ? $message
            : 'We could not write this one. Nothing extra has been charged — please try again.';
    }

    /** The page is watching this while it waits. */
    private function finish(string $gameId, string $status, ?string $message = null): void
    {
        Cache::put(self::statusKey($gameId), ['status' => $status, 'message' => $message], now()->addHour());
    }

    public static function statusKey(string $gameId): string
    {
        return "game-writing:{$gameId}";
    }

    /**
     * Where a game is up to: working, ready, or failed with a reason.
     *
     * @return array{status: string, message: ?string}
     */
    public static function statusOf(string $gameId): array
    {
        return (array) Cache::get(self::statusKey($gameId), ['status' => 'idle', 'message' => null]);
    }

    public static function markWorking(string $gameId): void
    {
        Cache::put(self::statusKey($gameId), ['status' => 'working', 'message' => null], now()->addHour());
    }
}
