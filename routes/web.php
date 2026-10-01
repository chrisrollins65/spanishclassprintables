<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\InternalPinAssetController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\PaddleWebhookController;
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

/*
 * The public pages a buyer — and Paddle's reviewer, before they approve the
 * account — has to be able to read without logging in.
 */
Route::view('/pricing', 'pages.pricing')->name('pricing');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/refunds', 'pages.refunds')->name('refunds');

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

/*
 * The shop's back office. One gate, on the whole group: nothing under /admin
 * is reachable without it (config/site.php names who has it).
 */
Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::get('/teachers/{user}', [AdminController::class, 'teacher'])->name('teacher');
    Route::post('/teachers/{user}/credits', [AdminController::class, 'credits'])->name('credits');
    Route::post('/purchases/{entry}/refund', [AdminController::class, 'refund'])->name('refund');
    Route::post('/games/{game}/lock', [AdminController::class, 'lock'])->name('lock');
    Route::post('/games/{game}/unlock', [AdminController::class, 'unlock'])->name('unlock');
});

/*
 * What Paddle tells us: a sale, a refund, a chargeback. Signed with its own
 * secret and no session, like the other machine-to-machine endpoints here.
 */
Route::post('/api/paddle/webhook', PaddleWebhookController::class)
    ->middleware('throttle:120,1');

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
        Route::get('/credits', [CreditsController::class, 'show'])->name('credits');
        Route::get('/credits/balance', [CreditsController::class, 'balance'])->name('credits.balance');

        Route::get('/my-games', [TeacherGameController::class, 'index'])->name('my-games');

        // Making a game: choosing what it is, then writing it — by hand, or by
        // asking a model. The credit goes at `store`, before anything is
        // written, so every model call has one behind it.
        Route::get('/my-games/new', [TeacherGameController::class, 'create'])->name('my-games.create');
        Route::post('/my-games', [TeacherGameController::class, 'store'])
            ->middleware('throttle:30,1')->name('my-games.store');
        Route::post('/my-games/{game}/write', [TeacherGameController::class, 'write'])
            ->middleware('throttle:20,1')->name('my-games.write');
        Route::get('/my-games/{game}/writing', [TeacherGameController::class, 'writingStatus'])->name('my-games.writing');
        // Tuning a game that already exists — the smaller, repeated half of
        // writing one, and what the per-credit AI budget is really for.
        Route::post('/my-games/{game}/ask', [TeacherGameController::class, 'ask'])
            ->middleware('throttle:20,1')->name('my-games.ask');
        Route::get('/my-games/{game}/asking', [TeacherGameController::class, 'askingStatus'])->name('my-games.asking');
        Route::post('/my-games/{game}/publish', [TeacherGameController::class, 'publish'])->name('my-games.publish');

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
        // Rendering is a whole browser, so asking for cards is capped well
        // below what one teacher could ever need.
        Route::post('/my-games/{game}/cards', [TeacherGameController::class, 'cards'])
            ->middleware('throttle:20,1')
            ->name('my-games.cards');
        Route::get('/my-games/{game}/cards/{size}/status', [TeacherGameController::class, 'cardsStatus'])
            ->whereNumber('size')->name('my-games.cards.status');
        Route::get('/my-games/{game}/cards/{size}.pdf', [TeacherGameController::class, 'cardsDownload'])
            ->whereNumber('size')->name('my-games.cards.download');

        // The quiz's equivalent of the cards: the page each team writes on.
        Route::post('/my-games/{game}/answer-sheet', [TeacherGameController::class, 'answerSheet'])
            ->middleware('throttle:20,1')->name('my-games.answer-sheet');
        Route::get('/my-games/{game}/answer-sheet/status', [TeacherGameController::class, 'answerSheetStatus'])
            ->name('my-games.answer-sheet.status');
        Route::get('/my-games/{game}/answer-sheet.pdf', [TeacherGameController::class, 'answerSheetDownload'])
            ->name('my-games.answer-sheet.download');

        Route::delete('/my-games/{game}', [TeacherGameController::class, 'destroy'])->name('my-games.destroy');
    });
});
