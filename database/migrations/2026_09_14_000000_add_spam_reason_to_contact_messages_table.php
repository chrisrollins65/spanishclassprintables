<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a contact message was held back from the inbox, or null for one that
 * was emailed. Suspicious messages are kept rather than deleted, so a real
 * teacher caught by a spam check can still be found here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('spam_reason', 50)->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('spam_reason');
        });
    }
};
