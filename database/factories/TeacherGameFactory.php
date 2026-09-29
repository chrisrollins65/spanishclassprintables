<?php

namespace Database\Factories;

use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherGame>
 */
class TeacherGameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'theme' => 'Los Deportes',
            'games' => 'jeopardy',
            'source_code' => null,
            'kind' => 'jeopardy',
            // Most tests are about a finished game; a draft is the exception
            // and asks for itself.
            'published_at' => now(),
            'payload' => json_encode([
                'theme' => 'Los Deportes',
                'games' => ['jeopardy' => ['title' => 'Los Deportes', 'categories' => []]],
            ]),
        ];
    }

    /** A game still being written: not playable, not printable. */
    public function draft(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }
}
