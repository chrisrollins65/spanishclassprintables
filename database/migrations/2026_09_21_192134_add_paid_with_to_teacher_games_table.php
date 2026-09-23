<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which spend on the ledger paid for this game.
 *
 * A game claimed from a TpT pack costs nothing and has none; a game created
 * with a credit does. It is what a refund looks at: only a game somebody paid
 * for can be taken back by taking the payment back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_games', function (Blueprint $table) {
            $table->foreignId('paid_with')->nullable()->after('source_code')
                ->constrained('credit_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_with');
        });
    }
};
