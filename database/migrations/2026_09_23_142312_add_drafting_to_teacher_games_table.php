<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drafts, and which credit is paying for a game.
 *
 * A game a teacher is still writing is a draft: not playable, not printable,
 * and deletable to get the credit back. Publishing is the moment it becomes
 * the thing they paid for, and the moment the credit stops being refundable.
 *
 * `paid_with` (the spend ledger row) stays for games created before this;
 * `credit_unit_id` is what new games use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_games', function (Blueprint $table) {
            $table->foreignId('credit_unit_id')->nullable()->after('paid_with')
                ->constrained('credit_units')->nullOnDelete();

            // Null for a game claimed from TpT, which was never a draft here.
            $table->timestamp('published_at')->nullable()->after('credit_unit_id');

            // bingo | jeopardy, chosen before anything is written and never
            // changed: a bingo bank is not a quiz board.
            $table->string('kind', 12)->nullable()->after('games');

            // What wrote it, for when a teacher says a pack reads oddly.
            $table->string('written_by', 40)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_unit_id');
            $table->dropColumn(['published_at', 'kind', 'written_by']);
        });
    }
};
