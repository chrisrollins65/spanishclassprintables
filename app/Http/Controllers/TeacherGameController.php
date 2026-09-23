<?php

namespace App\Http\Controllers;

use App\Jobs\RenderBingoCards;
use App\Models\Room;
use App\Models\TeacherGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A teacher's own games: the list, claiming a bought game by its room code,
 * and playing one. See docs/teacher-games.md, Phase 1.
 *
 * Every game is looked up through the logged-in teacher's own relation, never
 * by id alone, so another teacher's game is simply not found: a 404, which
 * also says nothing about whether that id exists.
 */
class TeacherGameController extends Controller
{
    /** The same ceiling a published room has (RoomController). */
    private const MAX_PAYLOAD_BYTES = 2_000_000;

    public function index(Request $request): View
    {
        // Everything the list shows except the payload: a bingo payload is
        // ~60KB and nothing here reads it. Anything a row displays has to be
        // named, or it silently reads as empty — which is how a locked game
        // first appeared on this page as a playable one.
        $games = $request->user()->teacherGames()
            ->select(['id', 'user_id', 'theme', 'games', 'source_code', 'locked_at', 'locked_reason', 'created_at', 'updated_at'])
            ->latest('updated_at')
            ->get();

        return view('games.index', ['games' => $games]);
    }

    /**
     * Copy a bought game into the teacher's account.
     *
     * The room code is the only proof of purchase there is: TpT gives sellers
     * no way to check a buyer. That is acceptable because anyone holding the
     * code can already play the game and see every answer; the copy adds only
     * the right to change it. Claims per code are counted by source_code.
     */
    public function claim(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $request->validate(
            ['code' => ['required', 'regex:/^[A-Z0-9]{4,8}$/']],
            ['code.regex' => 'A game code is 4 to 8 letters and numbers.'],
        );
        $code = $request->input('code');

        // The demo games are free to everyone, so a copy of one would be a free
        // game once games are sold here (site.unclaimable_codes).
        if (in_array($code, (array) config('site.unclaimable_codes'), true)) {
            return back()->withInput()->withErrors([
                'code' => "That's the demo game, which can't be customized. Use the code from a game you bought.",
            ]);
        }

        $room = Room::find($code);
        if ($room === null) {
            return back()->withInput()->withErrors([
                'code' => "We couldn't find a game with that code. It's printed on the Play Online page of your download.",
            ]);
        }

        // Claiming the same game twice would leave two copies to tell apart.
        $existing = $request->user()->teacherGames()->where('source_code', $code)->first();
        if ($existing !== null) {
            return redirect()->route('my-games')
                ->with('status', "\"{$existing->theme}\" is already in your games.");
        }

        $game = TeacherGame::copyOf($room);
        $request->user()->teacherGames()->save($game);

        return redirect()->route('my-games')
            ->with('status', "\"{$game->theme}\" is now in your games.");
    }

    public function edit(Request $request, string $game): View
    {
        return view('games.edit', ['game' => $this->playable($request, $game)]);
    }

    /**
     * Save an edited game.
     *
     * The editor in the browser judges the writing — it runs the builder's own
     * validators (public/game/shared) and shows what they say — while this
     * checks only the shape and the size. A second copy of every writing rule
     * in PHP would be a copy to keep in step with the builder for no gain: a
     * payload that breaks a writing rule breaks nobody's game but the owner's.
     */
    public function update(Request $request, string $game): JsonResponse
    {
        $game = $this->playable($request, $game);

        $request->validate([
            'payload' => ['required', 'array'],
            'payload.theme' => ['required', 'string', 'max:120'],
            'payload.games' => ['required', 'array', 'min:1'],
            'payload.items' => ['sometimes', 'array', 'max:500'],
        ]);

        /* The whole payload, not the validated subset.
         *
         * A payload carries more than the editor touches — `clueTypes`, which
         * decides how bingo calls a word, `reference`, `grades` — and taking
         * only the validated keys quietly dropped every one of them on the
         * first save. Validation here says what must be present, never what may
         * be kept.
         */
        $payload = (array) $request->input('payload');

        $unknown = array_diff(array_keys($payload['games']), array_keys(TeacherGame::LABELS));
        if ($unknown !== []) {
            return response()->json(['message' => 'That is not a game this site can play.'], 422);
        }

        // The copy's code is this game's id and nothing else decides it: the
        // browser could otherwise point this game — and the teacher's saved
        // progress in it — at somebody else's room.
        $payload['code'] = $game->id;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > self::MAX_PAYLOAD_BYTES) {
            return response()->json(['message' => 'That game is too big to save.'], 422);
        }

        $game->update([
            'theme' => $payload['theme'],
            'games' => implode(',', array_keys($payload['games'])),
            'payload' => $json,
        ]);

        return response()->json(['saved_at' => $game->updated_at->toIso8601String()]);
    }

    public function play(Request $request, string $game): Response
    {
        $game = $this->playable($request, $game);

        return response(RoomController::shell(route('my-games.payload', $game)))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * The game's data, for the shell.
     *
     * Not cached anywhere, unlike a room's: a teacher who edits a clue and
     * presses play expects the clue they just typed.
     */
    public function payload(Request $request, string $game): Response
    {
        $game = $this->playable($request, $game);

        return response($game->payload)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Ask for a set of bingo cards as a PDF.
     *
     * The file is named after what the cards were when it was made, so asking
     * twice for an unedited game is a download rather than a second render,
     * and an edit makes a new name rather than serving yesterday's words. The
     * render itself is queued: it is a whole browser, and one worker runs.
     */
    public function cards(Request $request, string $game): JsonResponse
    {
        $game = $this->playable($request, $game);
        $size = (int) $request->input('size');
        $stamp = $this->cardsStamp($game, $size);

        if ($stamp === null) {
            return response()->json(['message' => 'This game has no cards that size.'], 422);
        }

        if (Storage::disk('local')->exists(RenderBingoCards::pathFor($game->id, $size, $stamp))) {
            return response()->json(['status' => 'ready', 'url' => route('my-games.cards.download', [$game, $size])]);
        }

        RenderBingoCards::dispatch($game->id, $size, $stamp);

        return response()->json(['status' => 'working'], 202);
    }

    /** Whether the cards asked for have finished rendering. */
    public function cardsStatus(Request $request, string $game, int $size): JsonResponse
    {
        $game = $this->playable($request, $game);
        $stamp = $this->cardsStamp($game, $size);

        $ready = $stamp !== null && Storage::disk('local')->exists(RenderBingoCards::pathFor($game->id, $size, $stamp));

        return response()->json([
            'status' => $ready ? 'ready' : 'working',
            'url' => $ready ? route('my-games.cards.download', [$game, $size]) : null,
        ]);
    }

    public function cardsDownload(Request $request, string $game, int $size): Response
    {
        $game = $this->playable($request, $game);
        $stamp = $this->cardsStamp($game, $size);
        abort_if($stamp === null, 404);

        $path = RenderBingoCards::pathFor($game->id, $size, $stamp);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $name = Str::slug($game->theme ?: 'bingo')."-cards-{$size}x{$size}.pdf";

        return Storage::disk('local')->download($path, $name);
    }

    /**
     * A fingerprint of the cards of one size: what they are, not when they
     * were made. Two teachers' games, or one game before and after an edit,
     * can never share a file.
     */
    private function cardsStamp(TeacherGame $game, int $size): ?string
    {
        $payload = json_decode($game->payload, true) ?: [];
        $cards = array_values(array_filter(
            $payload['games']['bingo']['cards'] ?? [],
            fn (array $card): bool => (int) ($card['size'] ?? 0) === $size,
        ));

        if ($cards === []) {
            return null;
        }

        return substr(sha1(json_encode([$game->theme, $cards])), 0, 16);
    }

    public function destroy(Request $request, string $game): RedirectResponse
    {
        $game = $this->owned($request, $game);
        $game->delete();

        return redirect()->route('my-games')
            ->with('status', "\"{$game->theme}\" was removed from your games.");
    }

    private function owned(Request $request, string $id): TeacherGame
    {
        return $request->user()->teacherGames()->findOrFail($id);
    }

    /**
     * The game, and only if it still belongs to the teacher in the sense that
     * matters: a refunded purchase locks what it paid for.
     *
     * 403 rather than 404 — the game is theirs and they can see it on the
     * list, so pretending it does not exist would be a lie they can check.
     */
    private function playable(Request $request, string $id): TeacherGame
    {
        $game = $this->owned($request, $id);

        abort_if($game->isLocked(), 403, 'This game is locked because its purchase was refunded.');

        return $game;
    }
}
