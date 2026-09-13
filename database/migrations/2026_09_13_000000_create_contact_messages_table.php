<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages from the homepage's contact form.
 *
 * Kept in the database, not only emailed: the site may have no mailer
 * configured, and a teacher who took the time to write should never be lost
 * to a bounced message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('topic', 20);
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
