<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Published game rooms.
 *
 * These live in the database rather than as files under storage/ for one
 * reason: backups. The nightly mysqldump already covers this table, whereas a
 * per-site storage directory has to be remembered and included by hand. A room
 * is the one piece of data whose loss breaks a link already printed inside a
 * packet somebody paid for, so it belongs where the backup is already looking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            // The code IS the identity — it is printed on the worksheets and in
            // their QR codes — so there is no surrogate key worth adding.
            $table->string('code', 8)->primary();
            $table->string('theme')->nullable();

            // Served back to the browser verbatim. longText rather than a json
            // column so the bytes that were published are the bytes that come
            // back, with no re-encoding in between.
            $table->longText('payload');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
