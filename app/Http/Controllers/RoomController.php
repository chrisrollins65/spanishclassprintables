<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Classroom game rooms.
 *
 * A room is one published packet: the codes printed on the worksheets and
 * encoded in their QR codes resolve here. Two rules shape everything below.
 *
 * First, a room link can never die. A teacher may print a packet today and
 * hand it out three years from now, and a dead link on a product someone paid
 * for is a refund and a one-star review. Payloads therefore live in the
 * database, which the nightly dump already backs up, rather than in files that
 * would have to be remembered separately — and never under public/, which each
 * release replaces.
 *
 * Second, the page itself is static. public/j is plain HTML and JS served by
 * nginx without touching PHP; this controller exists only for the pretty room
 * URL, which is not a file on disk, and for the payload behind it.
 */
class RoomController extends Controller
{
    /** Room codes are printed and read aloud, so they stay short and unambiguous. */
    private const CODE_PATTERN = '/^[A-Z0-9]{4,8}$/';

    /** Generous for a 96-card deck (~60KB) and still a hard stop on abuse. */
    private const MAX_PAYLOAD_BYTES = 2_000_000;

    /**
     * The room page. Every code serves the same static shell — the shell reads
     * the code out of the URL and fetches its own payload.
     *
     * The shell and its assets live under public/game, NOT public/j, and that
     * separation is load-bearing. A real directory at public/j shadows these
     * routes: a server resolves /j/ANYCODE against the directory first, hands
     * the framework only the leftover path segment, and every room URL 404s.
     * With nothing on disk at /j, the whole prefix belongs to the router.
     */
    public function show(?string $code = null): Response
    {
        $shell = public_path('game/room.html');
        abort_unless(is_file($shell), 404);

        return response(file_get_contents($shell))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * The room's data. Fetched by the shell as `rooms/{CODE}.json`, relative to
     * /j/, so this path has to match what the page asks for.
     */
    public function payload(string $code): Response
    {
        $code = strtoupper($code);
        abort_unless(preg_match(self::CODE_PATTERN, $code), 404);

        $room = Room::find($code);
        abort_unless($room !== null, 404);

        // Published rooms are immutable in practice but can be republished, so
        // cache briefly rather than forever: a corrected packet should reach a
        // classroom the same day, not next term.
        return response($room->payload)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Publish or replace a room, called by the local packet builder.
     *
     * Same shared-secret shape as Eat Well Planner's pin drop-box, because the
     * caller is the same machine doing the same kind of one-way push. There is
     * deliberately no delete: nothing here should ever be able to break a link
     * that is already printed on paper.
     */
    public function publish(Request $request): Response
    {
        $secret = (string) config('rooms.publish_secret');
        abort_if($secret === '', 503, 'Room publishing is not configured.');

        $given = (string) $request->header('X-Room-Secret', '');
        abort_unless(hash_equals($secret, $given), 403);

        $code = strtoupper((string) $request->input('code', ''));
        abort_unless(preg_match(self::CODE_PATTERN, $code), 422, 'Invalid room code.');

        $payload = $request->input('payload');
        abort_unless(is_array($payload), 422, 'Missing payload.');

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        abort_if($json === false, 422, 'Payload is not encodable.');
        abort_if(strlen($json) > self::MAX_PAYLOAD_BYTES, 413, 'Payload too large.');

        Room::updateOrCreate(
            ['code' => $code],
            ['theme' => (string) ($payload['theme'] ?? ''), 'payload' => $json],
        );

        return response()->json([
            'code' => $code,
            'url'  => url("/j/{$code}"),
            'bytes' => strlen($json),
        ]);
    }
}
