<?php

namespace App\Models;

use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a teacher told us about a game.
 *
 * The rating is saved the moment it is tapped and the rest arrives later, if it
 * arrives, against the same token — see FeedbackController::save().
 */
class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use HasFactory, MassPrunable;

    /** Kept as long as a contact message, for the same reason. The policy names this. */
    public const KEEP_YEARS = 2;

    /** What each rating means. public/game/ui.js and the feedback page show the same three. */
    public const RATINGS = [
        3 => ['face' => '😀', 'label' => 'Loved it'],
        2 => ['face' => '😐', 'label' => 'It was OK'],
        1 => ['face' => '😕', 'label' => 'Had problems'],
    ];

    /** The quick answers to "what got in the way?". ui.js carries the same list. */
    public const ISSUES = [
        'setup' => 'Setup was confusing',
        'audio' => 'Audio didn’t work',
        'hard' => 'Too hard for my class',
        'easy' => 'Too easy for my class',
        'long' => 'Took too long',
        'broke' => 'Something broke',
    ];

    /** The quick answers to "what worked best?", asked first after "Loved it". ui.js carries the same list. */
    public const HIGHLIGHTS = [
        'engaged' => 'Students were engaged',
        'setup' => 'Easy to set up',
        'practice' => 'Good practice',
        'level' => 'Right level for my class',
        'time' => 'Saved me time',
    ];

    protected $table = 'feedback';

    protected $fillable = ['token', 'user_id', 'game', 'room_code', 'teacher_game_id', 'source', 'rating', 'issues', 'highlights', 'comment', 'email', 'spam_reason'];

    protected function casts(): array
    {
        return ['issues' => 'array', 'highlights' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subYears(self::KEEP_YEARS));
    }

    public function ratingLabel(): string
    {
        return self::RATINGS[$this->rating]['label'] ?? (string) $this->rating;
    }

    public function ratingFace(): string
    {
        return self::RATINGS[$this->rating]['face'] ?? '';
    }

    /** @return list<string> */
    public function issueLabels(): array
    {
        return array_values(array_intersect_key(self::ISSUES, array_flip($this->issues ?? [])));
    }

    /** @return list<string> */
    public function highlightLabels(): array
    {
        return array_values(array_intersect_key(self::HIGHLIGHTS, array_flip($this->highlights ?? [])));
    }
}
