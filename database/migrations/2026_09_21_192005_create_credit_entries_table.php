<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every credit a teacher has ever gained or spent, one row each.
 *
 * A ledger rather than a balance column, because the questions that matter are
 * historical: which purchase paid for this game, what a refund should take
 * back, why someone has three credits. A balance is the sum of the rows and
 * can always be recomputed; a column can only be wrong.
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

            // Positive for a purchase or a gift, negative for a game made or a
            // refund taken back.
            $table->integer('delta');

            // purchase | spend | refund | admin
            $table->string('reason', 20);

            /*
             * What this row is about: Paddle's transaction id for a purchase or
             * a refund, the game's id for a spend. Together with the reason it
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
