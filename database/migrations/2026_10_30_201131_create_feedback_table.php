<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            // One per asking, made where the question is shown: what turns the
            // tap on a face and the comment after it into one row, and two
            // halves of a double-tap into one answer.
            $table->string('token', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('game', 16)->nullable();
            $table->string('room_code', 8)->nullable()->index();
            $table->string('teacher_game_id', 26)->nullable()->index();
            $table->string('source', 16);
            $table->unsignedTinyInteger('rating');
            $table->json('issues')->nullable();
            $table->text('comment')->nullable();
            $table->string('email')->nullable();
            $table->string('spam_reason')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
