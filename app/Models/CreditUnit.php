<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One credit, and what it has spent on AI (docs/teacher-games.md, Phase 2).
 *
 * What a credit is doing is read off the game it points at rather than stored
 * beside it: no game means available, a draft means held, a published game
 * means spent. One fact, so it cannot disagree with itself, and a unique index
 * on game_id is the rule that a credit buys one game.
 *
 * `ai_cents_used` is the exception, deliberately: it survives the game it paid
 * for being deleted, which is what stops a teacher generating, deleting and
 * generating again on one credit forever.
 */
class CreditUnit extends Model
{
    protected $fillable = ['user_id', 'credit_entry_id', 'game_id', 'ai_cents_used'];

    protected function casts(): array
    {
        return ['ai_cents_used' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CreditEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(CreditEntry::class, 'credit_entry_id');
    }

    /**
     * @return BelongsTo<TeacherGame, $this>
     *
     * withTrashed: a refunded game is kept rather than destroyed, and its
     * credit should still say what it went on rather than reading as free.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(TeacherGame::class, 'game_id')->withTrashed();
    }

    /**
     * Put one of this teacher's free credits on a game.
     *
     * The update is the claim, not a read followed by a write: two tabs
     * pressing "create" at the same moment would otherwise be handed the same
     * credit and one game would end up free. A row someone else took between
     * the read and the update matches nothing, and we look again.
     */
    public static function claimFor(User $user, TeacherGame $game): ?self
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $id = self::where('user_id', $user->id)
                ->whereNull('game_id')
                ->orderBy('id')
                ->value('id');

            if ($id === null) {
                return null;
            }

            $taken = self::where('id', $id)->whereNull('game_id')->update(['game_id' => $game->id]);

            if ($taken === 1) {
                return self::find($id);
            }
        }

        return null;
    }

    /**
     * Hand the credit back, which deleting a draft does.
     *
     * Explicit rather than left to the foreign key: soft-deleting a game is an
     * update, not a delete, so "null on delete" never fires — and a rule this
     * load-bearing belongs where a reader of the controller can see it.
     */
    public function release(): void
    {
        $this->update(['game_id' => null]);
    }

    /** What is left of this credit's AI budget, in cents. */
    public function aiCentsLeft(): int
    {
        return max(0, (int) config('ai.cents_per_credit') - $this->ai_cents_used);
    }

    /** How much of it has gone, as a percentage, for the meter. */
    public function aiPercentUsed(): int
    {
        $budget = max(1, (int) config('ai.cents_per_credit'));

        return (int) min(100, round($this->ai_cents_used / $budget * 100));
    }

    public function chargeAi(int $cents): void
    {
        $this->increment('ai_cents_used', max(0, $cents));
    }
}
