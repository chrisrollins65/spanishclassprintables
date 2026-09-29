<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which purchases have been refunded, on the purchase itself.
 *
 * A refund is its own ledger row, but nothing on the purchase said so — and
 * `paddle:sync`, which exists to put right whatever went missing, needs to
 * know the difference between a purchase whose credits were never created and
 * one whose credits were deliberately taken away. Without this it would hand
 * back the credits from every refund it ever found.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_entries', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('credit_entries', function (Blueprint $table) {
            $table->dropColumn('refunded_at');
        });
    }
};
