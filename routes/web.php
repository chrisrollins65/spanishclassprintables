<?php

use App\Http\Controllers\Api\InternalPinAssetController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TeacherGameController;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Route;

/*
 * The homepage is where a buyer lands from the address printed on a packet:
 * thanks and a review ask first, then the room code box, then contact.
 */
Route::get('/', fn () => view('home', [
    'topics' => ContactMessage::TOPICS,
    'contactStarted' => ContactController::startedToken(),
    'turnstile' => ContactController::turnstileEnabled(),
]));

// A teacher has no reason to send more than a couple; this only stops floods.
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,10');

/*
 * Classroom game rooms.
 *
 * The game shell and its assets live in public/game and are served by the web
 * server without reaching PHP. Only these paths need the framework: the pretty
 * room URL, which is not a file on disk; the payload behind it, which lives in
 * the database so it survives deploys; and the builder's publish call. Nothing
 * may exist on disk at public/j, or it would shadow these routes.
 *
 * Asset paths in the page are absolute (/game/...) rather than relative, so a
 * trailing slash on a room URL cannot resolve them one directory too deep. A
 * redirect route would not work here anyway: Laravel normalises the trailing
 * slash away, so /j/{code}/ and /j/{code} are the same route and the redirect
 * would point at itself.
 */
Route::get('/j/rooms/{code}.json', [RoomController::class, 'payload'])
    ->where('code', '[A-Za-z0-9]{4,8}');

Route::get('/j', [RoomController::class, 'show']);

Route::get('/j/{code}', [RoomController::class, 'show'])
    ->where('code', '[A-Za-z0-9]{4,8}');

Route::post('/api/internal/room', [RoomController::class, 'publish']);

// Pin image drop-box: the TpT builder pushes rendered pin PNGs here so
// Pinterest's bulk uploader has a public URL to fetch them from. Guarded by its
// own shared secret (see InternalPinAssetController).
Route::post('/api/internal/pin-asset', [InternalPinAssetController::class, 'apiPostPinAsset'])
    ->middleware('throttle:60,1');

/*
 * Teacher accounts (docs/teacher-games.md). Fortify registers login, sign-up,
 * reset and verification itself; these are the pages it leaves to us.
 *
 * Account settings need only a login, not a confirmed address: a teacher who
 * typed their email wrong has to be able to reach the page that fixes it.
 */
Route::middleware('auth')->group(function () {
    Route::view('/account', 'account.settings')->name('account');

    Route::middleware('verified')->group(function () {
        Route::get('/my-games', [TeacherGameController::class, 'index'])->name('my-games');

        // A code that isn't found says so, which makes this a way to test
        // codes — but no better a way than /j/{code}, which is open to anyone.
        // The limit is for scripts, not for a teacher mistyping.
        Route::post('/my-games/claim', [TeacherGameController::class, 'claim'])
            ->middleware('throttle:10,1')
            ->name('my-games.claim');

        Route::get('/my-games/{game}/edit', [TeacherGameController::class, 'edit'])->name('my-games.edit');
        Route::put('/my-games/{game}', [TeacherGameController::class, 'update'])->name('my-games.update');
        Route::get('/my-games/{game}/play', [TeacherGameController::class, 'play'])->name('my-games.play');
        Route::get('/my-games/{game}/payload.json', [TeacherGameController::class, 'payload'])->name('my-games.payload');
        Route::delete('/my-games/{game}', [TeacherGameController::class, 'destroy'])->name('my-games.destroy');
    });
});
