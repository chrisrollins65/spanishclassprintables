<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Catch anything Paddle's webhooks missed.
 *
 * A webhook is a push and a push can be missed — a deploy mid-delivery, an
 * endpoint down, a retry budget that ran out. This asks Paddle instead, and
 * every row it writes is keyed by the Paddle id that caused it, so an hour
 * where nothing was missed does nothing at all.
 */
Schedule::command('paddle:sync')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
