<?php

namespace Database\Factories;

use App\Models\Logbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Logbook>
 */
class LogbookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
            'date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
        ];
    }
}
