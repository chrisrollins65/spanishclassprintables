<?php

use App\Http\Controllers\Api\InternalPinAssetController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
