<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
/**
 * A teacher with an account: the owner of the games they claim or create.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<TeacherGame, $this> */
    public function teacherGames(): HasMany
    {
        return $this->hasMany(TeacherGame::class);
    }

    /** @return HasMany<CreditEntry, $this> */
    public function creditEntries(): HasMany
    {
        return $this->hasMany(CreditEntry::class);
    }

    /** What the ledger adds up to. Never stored: see the credit_entries migration. */
    public function credits(): int
    {
        return (int) $this->creditEntries()->sum('delta');
    }

    /** @return HasMany<CreditUnit, $this> */
    public function creditUnits(): HasMany
    {
        return $this->hasMany(CreditUnit::class);
    }

    /** Credits with no game on them — what "you have 3 credits" means. */
    public function availableCredits(): int
    {
        return $this->creditUnits()->whereNull('game_id')->count();
    }

    /** Credits a draft is sitting on, which a teacher can free by deleting it. */
    public function heldCredits(): int
    {
        return $this->creditUnits()
            ->whereHas('game', fn ($query) => $query->whereNull('published_at'))
            ->count();
    }

    /**
     * Give credits: a ledger row for the money, and one unit per credit for
     * the teacher to spend.
     *
     * Both or neither — a ledger that says +10 with no units to spend is a
     * teacher who paid and got nothing.
     */
    public function grantCredits(int $credits, string $reason, ?string $reference = null, ?string $note = null): ?CreditEntry
    {
        return DB::transaction(function () use ($credits, $reason, $reference, $note): ?CreditEntry {
            $entry = CreditEntry::record($this, $credits, $reason, $reference, $note);

            // Already granted: Paddle delivered this twice, or paddle:sync
            // found what the webhook had already handled.
            if ($entry === null) {
                return null;
            }

            foreach (range(1, max(1, $credits)) as $ignored) {
                $this->creditUnits()->create(['credit_entry_id' => $entry->id]);
            }

            return $entry;
        });
    }

    /**
     * Take credits back when a purchase is refunded.
     *
     * What happens depends on what the credits were doing:
     *
     *  · untouched — the credit simply goes
     *  · held by a draft — the credit goes and the draft stays, unpaid for.
     *    Drafts cost nothing to keep, and publishing will ask for a credit.
     *  · spent on a published game — the game is taken out of the teacher's
     *    list, and kept. Refunds get reversed (Paddle has chargeback_reverse),
     *    refunds get issued by mistake, and a deleted game is also deleted
     *    evidence if the refund turns into an argument. An admin can give it
     *    back.
     *
     * Only the credits THIS purchase created, which is what `credit_entry_id`
     * on each unit is for. Taking "the newest few" instead would let a refund
     * of one purchase eat credits that came from another — or worse, ones an
     * admin gave as an apology.
     *
     * Among them, untouched credits go first and published games last, so a
     * teacher loses a working game only when the refund leaves no other way to
     * balance the books.
     */
    public function revokeCredits(CreditEntry $purchase, string $reference, string $note): void
    {
        $taken = CreditEntry::record($this, -abs($purchase->delta), CreditEntry::REFUND, $reference, $note);
        if ($taken === null) {
            return; // Already handled — Paddle delivered this twice.
        }

        $units = $this->creditUnits()
            ->where('credit_entry_id', $purchase->id)
            ->get()
            ->sortBy(fn (CreditUnit $unit): int => match (true) {
                $unit->game === null => 0,          // free
                ! $unit->game->isPublished() => 1,  // a draft
                default => 2,                       // a game they are using
            })
            ->values();

        foreach ($units as $unit) {
            $game = $unit->game;

            // A published game is taken out of the list and kept; a draft
            // keeps its writing and simply stops being paid for.
            if ($game !== null && $game->isPublished()) {
                $game->lock($note);
                $game->delete();
            }

            $unit->delete();
        }

        // On the purchase itself, so paddle:sync can tell "these credits were
        // never created" from "these credits were taken away".
        $purchase->forceFill(['refunded_at' => now()])->save();
    }

    /** Admin rights come from config/site.php, never from this row. */
    public function isAdmin(): bool
    {
        return in_array(strtolower($this->email), (array) config('site.admins'), true);
    }
}
