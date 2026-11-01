<?php

namespace Database\Factories;

use App\Models\GameEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameEvent>
 */
class GameEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => 'game_started',
            'play_id' => fake()->uuid(),
            'game' => 'jeopardy',
            'room_code' => 'DEMO1',
        ];
    }
}
