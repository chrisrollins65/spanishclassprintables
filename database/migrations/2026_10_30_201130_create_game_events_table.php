<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->string('play_id', 36)->nullable()->index();
            $table->string('game', 16)->nullable();
            $table->string('room_code', 8)->nullable()->index();
            // No foreign key: a game's history outlives the game, and nothing
            // here should ever stand in the way of deleting one.
            $table->string('teacher_game_id', 26)->nullable()->index();
            // Nulled rather than deleted with the account: what is left says
            // that a game was played, and no longer by whom.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
