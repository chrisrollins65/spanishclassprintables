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
            'payload' => json_encode([
                'theme' => 'Los Deportes',
                'games' => ['jeopardy' => ['title' => 'Los Deportes', 'categories' => []]],
            ]),
        ];
    }
}
