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

/*
 * Nightly backup to Google Drive. The database holds everything a buyer paid
 * for — their credits, the games they edited themselves, and the rooms every
 * printed QR code points at. A room that cannot be restored is a dead QR on a
 * PDF someone has already sold.
 *
 * Monitor runs BEFORE tonight's backup, not after. It grades the newest
 * archive that exists, so going first asks "did last night work?" and catches
 * a cron that has stopped firing — the one failure a backup job cannot report
 * on itself. Going last, it would only ever grade the upload a minute old.
 *
 * Clean before running so the retention sweep cannot delete what was just
 * uploaded, and so the night's archive has room under the size cap.
 */
Schedule::command('backup:monitor')->dailyAt('00:50');
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
