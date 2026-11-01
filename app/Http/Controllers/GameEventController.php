<?php

namespace App\Http\Controllers;

use App\Models\GameEvent;
use App\Models\Room;
use App\Models\TeacherGame;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Where the game screens report what happened (public/game/analytics.js).
 *
 * Open to anyone, because a room is: a code on a worksheet, no account and no
 * CSRF token. So everything is checked against what exists — a listed event, a
 * real room, a game this teacher owns — and whatever fails is dropped with the
 * same empty reply. A classroom never needs to hear about any of it.
 */
class GameEventController extends Controller
{
    /** More than any page holds between two sends; only stops a flood. */
    private const MAX_BATCH = 30;

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'play_id' => ['required', 'string', 'max:36'],
            'room_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{4,8}$/'],
            'own_game' => ['nullable', 'string', 'size:26'],
            'events' => ['required', 'array', 'max:'.self::MAX_BATCH],
            'events.*.event' => ['required', 'string', 'max:40'],
            'events.*.game' => ['nullable', 'string', 'in:'.implode(',', array_keys(TeacherGame::LABELS))],
            'events.*.data' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        // Our own testing would otherwise be the loudest teacher in the data.
        if ($validator->fails() || $user?->isAdmin()) {
            return response()->noContent();
        }

        $fields = $validator->validated();
        $where = $this->whereItHappened($request, $fields);

        $rows = [];
        foreach ($fields['events'] as $reported) {
            $event = $reported['event'];

            // A wrong code is the one report that has no room, by definition.
            if (! array_key_exists($event, GameEvent::BROWSER_EVENTS) || ($where === null && $event !== 'code_not_found')) {
                continue;
            }

            $data = GameEvent::cleanData($event, $reported['data'] ?? []);

            $rows[] = [
                'event' => $event,
                'play_id' => $fields['play_id'],
                'game' => $reported['game'] ?? null,
                'room_code' => $where['room_code'] ?? null,
                'teacher_game_id' => $where['teacher_game_id'] ?? null,
                'user_id' => $user?->id,
                'data' => $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ];
        }

        if ($rows !== []) {
            GameEvent::insert($rows);
        }

        return response()->noContent();
    }

    /**
     * The room or game this batch is about, or null if there is no such thing.
     *
     * @param  array<string, mixed>  $fields
     * @return array{room_code?: string, teacher_game_id?: string}|null
     */
    private function whereItHappened(Request $request, array $fields): ?array
    {
        $ownGame = $fields['own_game'] ?? null;
        if ($ownGame !== null) {
            $owned = $request->user()?->teacherGames()->whereKey($ownGame)->exists();

            return $owned ? ['teacher_game_id' => $ownGame] : null;
        }

        $code = $fields['room_code'] ?? null;

        return $code !== null && Room::whereKey($code)->exists() ? ['room_code' => $code] : null;
    }
}
