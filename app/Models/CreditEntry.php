<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * One movement of credits (docs/teacher-games.md, Phase 2).
 *
 * Rows are only ever added. A refund is a negative row beside the purchase it
 * reverses, so the history reads as what happened rather than as what is left.
 */
class CreditEntry extends Model
{
    public const PURCHASE = 'purchase';

    public const SPEND = 'spend';

    public const REFUND = 'refund';

    public const ADMIN = 'admin';

    protected $fillable = ['user_id', 'delta', 'reason', 'reference', 'note'];

    protected function casts(): array
    {
        return ['delta' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add a row unless its reason and reference are already on the ledger.
     *
     * Paddle retries a webhook it did not hear back from, and a teacher can
     * double-click anything, so every caller here is expected to run twice.
     * The unique index is what decides it, not a read beforehand: two requests
     * arriving together would both find nothing.
     */
    public static function record(User $user, int $delta, string $reason, ?string $reference = null, ?string $note = null): ?self
    {
        try {
            return DB::transaction(fn (): self => self::create([
                'user_id' => $user->id,
                'delta' => $delta,
                'reason' => $reason,
                'reference' => $reference,
                'note' => $note,
            ]));
        } catch (QueryException $e) {
            // 23000: the unique index did its job — this has already happened.
            if ($e->getCode() === '23000') {
                return null;
            }

            throw $e;
        }
    }
}
