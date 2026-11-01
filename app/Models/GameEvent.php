<?php

namespace App\Models;

use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Throwable;

/**
 * One thing that happened: a game started, a clue nobody got, a page that
 * broke. What the admin's Insights page is read from.
 *
 * Milestones, never clicks. This shares a small server, and "how long did the
 * class stay on square three" is not a question anyone is asking.
 *
 * Nothing about a pupil may ever land here. The privacy policy says so, and the
 * thing that keeps it true is BROWSER_EVENTS: a key that is not listed is
 * dropped on the server, so a team name — which a teacher may well type as a
 * child's name — cannot arrive even if a later version of a game sends one.
 */
class GameEvent extends Model
{
    /** @use HasFactory<GameEventFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /** The privacy policy names this number. */
    public const KEEP_MONTHS = 13;

    /** How long a string in `data` may be. */
    private const MAX_VALUE_LENGTH = 200;

    /**
     * What a game may report, and the only keys each may carry.
     *
     * @var array<string, list<string>>
     */
    public const BROWSER_EVENTS = [
        'room_opened' => [],
        'code_not_found' => ['code'],
        'game_started' => ['mode', 'pattern', 'teams', 'priced'],
        'game_resumed' => [],
        'game_finished' => ['duration', 'teams', 'calls', 'how'],
        'how_to_opened' => [],
        'vocab_reviewed' => [],
        'clue_resolved' => ['category', 'clue', 'value', 'answered'],
        'bingo_checked' => ['valid'],
        'no_spanish_voice' => [],
        'js_error' => ['message', 'source', 'line'],
    ];

    protected $fillable = ['event', 'play_id', 'game', 'room_code', 'teacher_game_id', 'user_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(self::KEEP_MONTHS));
    }

    /**
     * Something the server itself saw happen to a teacher's game.
     *
     * Never allowed to cost the teacher anything: a failed insert here must not
     * fail the publish, or the paid AI edit, that it was only taking note of.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(string $event, TeacherGame $game, array $data = []): ?self
    {
        try {
            if ($game->user?->isAdmin()) {
                return null;
            }

            return self::create([
                'event' => $event,
                'game' => $game->kind,
                'teacher_game_id' => $game->id,
                'user_id' => $game->user_id,
                'data' => $data ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Only the keys this event may carry, as plain and short values.
     *
     * @param  array<mixed>  $data
     * @return array<string, scalar>|null
     */
    public static function cleanData(string $event, array $data): ?array
    {
        $allowed = array_flip(self::BROWSER_EVENTS[$event] ?? []);

        $clean = array_map(
            fn (mixed $value): mixed => is_string($value) ? Str::limit($value, self::MAX_VALUE_LENGTH) : $value,
            array_filter(array_intersect_key($data, $allowed), 'is_scalar'),
        );

        return $clean ?: null;
    }

    /** What this game is called, for a person reading about it. */
    public function theme(): ?string
    {
        if ($this->teacher_game_id !== null) {
            return TeacherGame::withTrashed()->whereKey($this->teacher_game_id)->value('theme');
        }

        return $this->room_code === null ? null : Room::whereKey($this->room_code)->value('theme');
    }
}
