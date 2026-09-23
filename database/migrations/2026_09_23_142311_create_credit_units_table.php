<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per credit a teacher holds.
 *
 * The ledger (credit_entries) records money: a purchase of ten is one row
 * saying +10. That is the right shape for reconciling with Paddle and the
 * wrong shape for the question this table answers — how much AI has this
 * credit paid for — because a budget has to attach to something countable.
 *
 * So a purchase of ten creates ten of these. A draft holds one; publishing
 * spends it; deleting a draft hands it back. What it does NOT hand back is
 * `ai_cents_used`: a teacher who generates, deletes and generates again is
 * drawing on the same budget, which is what stops one credit buying an
 * unlimited amount of writing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The purchase (or gift) that created it, so a refund can find its
            // own units and nobody else's.
            $table->foreignId('credit_entry_id')->constrained('credit_entries')->cascadeOnDelete();

            // available | held | spent
            $table->string('state', 12)->default('available');

            // What this credit's generations have cost us so far, in cents.
            // Survives the game it was spent on being deleted.
            $table->unsignedInteger('ai_cents_used')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_units');
    }
};
