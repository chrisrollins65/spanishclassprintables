<?php

namespace App\Console\Commands;

use App\Mail\HowDidItGo;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Ask each teacher, once, how their first game went.
 *
 * The day after, because that is when they are out of the classroom with a
 * minute to answer. Once per account and marked whether or not they reply: this
 * is a question, not a campaign. Someone who has already told us how it went
 * has nothing left to be asked.
 */
class AskForFeedback extends Command
{
    protected $signature = 'feedback:ask';

    protected $description = 'Email teachers who played their first game a day ago to ask how it went';

    /** Old enough that the lesson is over. */
    private const WAIT_HOURS = 24;

    /** Past this it is a stale question about a lesson nobody remembers. */
    private const GIVE_UP_DAYS = 7;

    /** Per run, so a first deploy cannot empty the mail allowance in one go. */
    private const MAX_A_RUN = 20;

    public function handle(): int
    {
        $teachers = User::query()
            ->whereNotNull('email_verified_at')
            ->whereNull('feedback_asked_at')
            ->whereDoesntHave('feedback')
            ->whereHas('gameEvents', fn ($plays) => $plays->where('event', 'game_started')
                ->where('created_at', '<=', now()->subHours(self::WAIT_HOURS))
                ->where('created_at', '>=', now()->subDays(self::GIVE_UP_DAYS)))
            ->limit(self::MAX_A_RUN)
            ->get();

        foreach ($teachers as $teacher) {
            $play = $teacher->gameEvents()->where('event', 'game_started')->latest('id')->first();

            try {
                Mail::to($teacher)->send(new HowDidItGo($teacher, $play));
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            $teacher->forceFill(['feedback_asked_at' => now()])->save();
        }

        return self::SUCCESS;
    }
}
