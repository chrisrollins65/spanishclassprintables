<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One fact instead of two.
 *
 * A credit used to carry a state — available, held, spent — beside a pointer
 * on the game saying which credit paid for it. Two places to keep in step, and
 * two ways to be wrong: a credit marked spent whose game is gone, or one
 * marked available that a game still points at.
 *
 * Now the credit points at its game, and the state is read off that: no game
 * means available, a game still in draft means held, a published game means
 * spent. The unique index is the rule that a credit buys one game.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_units', function (Blueprint $table) {
            $table->foreignUlid('game_id')->nullable()->after('credit_entry_id')
                ->constrained('teacher_games')->nullOnDelete();
            $table->unique('game_id');

            // The index goes first: SQLite refuses to drop a column an index
            // still mentions, where MySQL quietly rewrites the index for you.
            $table->dropIndex(['user_id', 'state']);
            $table->dropColumn('state');
        });

        Schema::table('teacher_games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('credit_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_id');
            $table->string('state', 12)->default('available');
        });

        Schema::table('teacher_games', function (Blueprint $table) {
            $table->foreignId('credit_unit_id')->nullable()->constrained('credit_units')->nullOnDelete();
        });
    }
};
