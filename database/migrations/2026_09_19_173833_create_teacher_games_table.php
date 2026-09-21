<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher's own games (docs/teacher-games.md).
 *
 * Deliberately not rows in `rooms`. A room is a code printed on a product in
 * other people's hands and must never change or disappear; a teacher game is
 * one teacher's private copy that they may edit or delete as they like. Keeping
 * them in separate tables means nothing a teacher does can reach a room.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_games', function (Blueprint $table) {
            // A ULID, not a room code: it goes in URLs, can't be guessed, and
            // can't be mistaken for a code printed on a product.
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('theme');

            // Which games the payload holds ("bingo", "jeopardy"), comma
            // separated, so the list page never has to load a payload.
            $table->string('games', 64);

            // The room it was claimed from; null for a game created from
            // scratch. Also how claims per code are counted.
            $table->string('source_code', 8)->nullable()->index();

            // Same shape as a room payload and served back verbatim, for the
            // same reason rooms use longText.
            $table->longText('payload');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_games');
    }
};
