<?php

namespace Database\Factories;

use App\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => fake()->uuid(),
            'game' => 'jeopardy',
            'room_code' => 'DEMO1',
            'source' => 'game',
            'rating' => 3,
        ];
    }
}
