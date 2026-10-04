<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every credit a teacher has ever been given or had taken back, one row each.
 *
 * A ledger rather than a balance column, because the questions that matter are
 * historical: which purchase paid for this game, what a refund should take
 * back, where someone's credits came from. Rows can always be re-derived from
 * Paddle; a column could only ever be corrected.
 *
 * What it does NOT record is a credit being spent. credit_units came after
 * this table and took that over: a credit is a row there, and what it is doing
 * is read off the game it points at. So the sum of these deltas is what a
 * teacher has BOUGHT, and `User::availableCredits()` is what they can spend.
 *
 * Rows are never edited or deleted. A refund is a negative row, not the
 * removal of the purchase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Positive for a purchase or a gift, negative for a refund taken
            // back. Never negative for a game made: see above.
            $table->integer('delta');

            // purchase | refund | admin
            $table->string('reason', 20);

            /*
             * What this row is about: Paddle's transaction id for a purchase
             * or a refund. Together with the reason it
             * is unique, which is what makes a webhook delivered twice — and
             * Paddle does retry — grant its credits once.
             */
            $table->string('reference', 64)->nullable();

            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['reason', 'reference']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_entries');
    }
};
