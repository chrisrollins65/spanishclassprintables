<?php

use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Classroom game rooms.
 *
 * The static page and its assets live in public/j and are served by nginx
 * without reaching PHP. Only these three paths need the framework: the pretty
 * room URL, which is not a file on disk; the payload behind it, which lives in
 * storage so it survives deploys; and the builder's publish call.
 *
 * Asset paths in the page are absolute (/j/...) rather than relative, so a
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
