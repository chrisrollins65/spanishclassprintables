<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A game can be locked rather than deleted.
 *
 * When a purchase is refunded, the credits it bought go back — and if they
 * have already been spent, the games they paid for stop working. Locked, not
 * deleted: a refund can be a mistake, a card may be half printed, and a teacher
 * who pays again should get their own writing back rather than a blank page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_games', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('payload');
            $table->string('locked_reason')->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_games', function (Blueprint $table) {
            $table->dropColumn(['locked_at', 'locked_reason']);
        });
    }
};
