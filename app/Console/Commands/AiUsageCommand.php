<?php

namespace App\Console\Commands;

use App\Models\TeacherGame;
use Illuminate\Console\Command;

/**
 * Move a game's AI spend by hand, so the meter and the "no AI left" state can
 * be seen without paying a model to reach them.
 *
 * Both of those only appear near the end of a credit's budget — the meter past
 * `ai.meter_from_percent`, the refusal at 100% — which is a dozen real edits
 * away and costs real money to arrive at. That is a long way to walk to look
 * at a warning, and the walk is the reason those two states went unlooked-at.
 *
 * Refuses to run in production: it rewrites a number that says what a teacher
 * has been charged for.
 */
class AiUsageCommand extends Command
{
    protected $signature = 'ai:usage
        {game? : The game id, or leave it out to list what is there}
        {--percent= : Set the spend to this percentage of one credit\'s budget}
        {--cents= : Set the spend to this many cents instead}
        {--reset : Put it back to nothing}';

    protected $description = 'Set a game\'s AI spend, to see the meter and the cut-off without spending money (local only)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Not in production: this rewrites what a teacher has been charged.');

            return self::FAILURE;
        }

        $budget = (int) config('ai.cents_per_credit');
        $id = $this->argument('game');

        if ($id === null) {
            return $this->listGames($budget);
        }

        $game = TeacherGame::withTrashed()->find($id);
        if ($game === null) {
            $this->error("No game with id {$id}.");

            return self::FAILURE;
        }

        $unit = $game->creditUnit;
        if ($unit === null) {
            $this->error('That game has no credit on it, so it has no AI budget to spend.');

            return self::FAILURE;
        }

        $cents = match (true) {
            (bool) $this->option('reset') => 0,
            $this->option('cents') !== null => max(0, (int) $this->option('cents')),
            $this->option('percent') !== null => (int) round($budget * (int) $this->option('percent') / 100),
            default => null,
        };

        if ($cents === null) {
            $this->line("\"{$game->theme}\" has used {$unit->ai_cents_used}c of {$budget}c ({$unit->aiPercentUsed()}%).");
            $this->comment('Pass --percent=80, --cents=40 or --reset to change it.');

            return self::SUCCESS;
        }

        // Set, not add: this is a dial to put the game where you want to look
        // at it, so asking for 80% twice leaves it at 80%.
        $unit->forceFill(['ai_cents_used' => $cents])->save();

        $this->info("\"{$game->theme}\" is now at {$unit->fresh()->aiPercentUsed()}% ({$cents}c of {$budget}c).");
        $this->line('Reload the editor to see it.');

        return self::SUCCESS;
    }

    private function listGames(int $budget): int
    {
        $games = TeacherGame::with('creditUnit')->latest('updated_at')->take(15)->get()
            ->filter(fn (TeacherGame $game): bool => $game->creditUnit !== null);

        if ($games->isEmpty()) {
            $this->comment('No games with a credit on them.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'theme', 'kind', 'AI used'],
            $games->map(fn (TeacherGame $game): array => [
                $game->id,
                $game->theme,
                $game->kind,
                $game->creditUnit->ai_cents_used."c of {$budget}c ({$game->creditUnit->aiPercentUsed()}%)",
            ])->all(),
        );

        $this->comment('php artisan ai:usage <id> --percent=80');

        return self::SUCCESS;
    }
}
