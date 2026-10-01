<?php

namespace Tests\Feature;

use App\Jobs\EditGame;
use App\Jobs\RenderAnswerSheet;
use App\Jobs\RenderBingoCards;
use App\Jobs\WriteGame;
use Tests\TestCase;

/**
 * The queue's retry window against the jobs that run in it.
 *
 * `retry_after` is how long the queue waits before deciding a job has died
 * and giving it to another worker. Set below a job's own timeout, it fires
 * while that job is STILL RUNNING and the work happens twice — which for
 * WriteGame means a second model call and a second charge against the
 * teacher's credit, the exact double-charge the rest of this app works to
 * prevent.
 *
 * It shipped that way: the stock 90 seconds sat under every job here. Nothing
 * caught it locally because a generation takes 20-40 seconds on a fast machine
 * and the worker was run by hand.
 */
class QueueTimeoutTest extends TestCase
{
    /** Every queued job that can outlive a short retry window. */
    private const JOBS = [
        WriteGame::class,
        EditGame::class,
        RenderBingoCards::class,
        RenderAnswerSheet::class,
    ];

    public function test_the_retry_window_outlasts_every_job(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');

        $this->assertGreaterThan(0, $retryAfter);

        foreach (self::JOBS as $job) {
            $timeout = (new \ReflectionClass($job))->getDefaultProperties()['timeout'] ?? 0;

            $this->assertGreaterThan(
                $timeout,
                $retryAfter,
                $job." runs for up to {$timeout}s, but the queue gives up on it after {$retryAfter}s "
                    .'and hands it to another worker — so it would run twice. Raise DB_QUEUE_RETRY_AFTER.',
            );
        }
    }

    /**
     * A job that spends a teacher's credit must never be retried on its own.
     *
     * One press, one charge: the queue cannot know whether a model call that
     * timed out had already been paid for, so it must not guess.
     */
    public function test_the_jobs_that_spend_money_do_not_retry(): void
    {
        foreach ([WriteGame::class, EditGame::class] as $job) {
            $this->assertSame(
                1,
                (new \ReflectionClass($job))->getDefaultProperties()['tries'] ?? null,
                $job.' must run once: a retry would charge the credit twice for one press.',
            );
        }
    }
}
