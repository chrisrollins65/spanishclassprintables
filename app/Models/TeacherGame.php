<?php

namespace App\Models;

use Database\Factories\TeacherGameFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One teacher's private game: a copy of a room they bought, or (later) one
 * they created. See docs/teacher-games.md.
 *
 * Its payload is a room payload whose `code` is this game's id, never the
 * room's. The games save their progress in the browser under that code, so a
 * copy sharing the room's code would pick up — and overwrite — the saved state
 * of the original game on the same computer.
 */
class TeacherGame extends Model
{
    /** @use HasFactory<TeacherGameFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** What each game in a payload is called on the teacher's pages. */
    public const LABELS = [
        'bingo' => 'Bingo',
        'jeopardy' => 'Team quiz',
    ];

    protected $fillable = ['theme', 'games', 'source_code', 'payload'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Build an unsaved copy of a room for a teacher.
     */
    public static function copyOf(Room $room): self
    {
        $game = new self;
        $game->id = $game->newUniqueId();

        $payload = json_decode($room->payload, true);
        $payload['code'] = $game->id;

        $game->fill([
            'theme' => (string) ($payload['theme'] ?? $room->theme ?? ''),
            'games' => implode(',', array_keys($payload['games'] ?? [])),
            'source_code' => $room->code,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $game;
    }

    /**
     * The games in this payload, named for the teacher.
     *
     * @return list<string>
     */
    public function gameLabels(): array
    {
        return array_values(array_map(
            fn (string $key): string => self::LABELS[$key] ?? ucfirst($key),
            array_filter(explode(',', $this->games)),
        ));
    }

    /**
     * Those names as one phrase. A pack sold on TpT holds one game, but a room
     * may hold both, and "Bingo · Team quiz" reads like two products.
     */
    public function gamesLabel(): string
    {
        $labels = $this->gameLabels();
        if (count($labels) < 2) {
            return $labels[0] ?? '';
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' and '.strtolower($last);
    }
}
