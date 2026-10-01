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
 *
 * Every step is guarded by a check that it still needs doing, because this
 * migration failed half-way on MySQL once and MySQL cannot roll DDL back: the
 * column had been added, the index had not been dropped, and nothing was
 * written to `migrations`. Re-running the original then died on a duplicate
 * column. Written this way it finishes a half-applied run as readily as a
 * fresh one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('credit_units', 'game_id')) {
            Schema::table('credit_units', function (Blueprint $table) {
                $table->foreignUlid('game_id')->nullable()->after('credit_entry_id')
                    ->constrained('teacher_games')->nullOnDelete();
                $table->unique('game_id');
            });
        }

        if (Schema::hasColumn('credit_units', 'state')) {
            /* The foreign key on user_id has to keep an index.
             *
             * This table was created with a composite index on
             * (user_id, state) AND a foreign key on user_id. MySQL does not
             * add a second index for the key when an existing one already
             * starts with that column — it adopts the composite. Dropping the
             * composite then fails with "needed in a foreign key constraint",
             * which is exactly how this migration broke in production.
             *
             * So user_id gets an index of its own first, and the composite is
             * free to go. SQLite has no such rule and is happy either way; it
             * only insists the index go before the column it names, which is
             * the order here regardless.
             */
            Schema::table('credit_units', function (Blueprint $table) {
                $table->index('user_id');
            });

            // A separate call, so the new index is certainly in place before
            // the old one goes, rather than merely earlier in a list.
            Schema::table('credit_units', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'state']);
                $table->dropColumn('state');
            });
        }

        if (Schema::hasColumn('teacher_games', 'credit_unit_id')) {
            Schema::table('teacher_games', function (Blueprint $table) {
                $table->dropConstrainedForeignId('credit_unit_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('credit_units', 'state')) {
            Schema::table('credit_units', function (Blueprint $table) {
                $table->string('state', 12)->default('available');
                $table->index(['user_id', 'state']);
            });

            // Only once the composite is back, for the same reason as above.
            Schema::table('credit_units', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
            });
        }

        if (Schema::hasColumn('credit_units', 'game_id')) {
            Schema::table('credit_units', function (Blueprint $table) {
                $table->dropConstrainedForeignId('game_id');
            });
        }

        if (! Schema::hasColumn('teacher_games', 'credit_unit_id')) {
            Schema::table('teacher_games', function (Blueprint $table) {
                $table->foreignId('credit_unit_id')->nullable()->constrained('credit_units')->nullOnDelete();
            });
        }
    }
};
