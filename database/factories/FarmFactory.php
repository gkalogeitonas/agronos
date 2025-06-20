<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Farm>
 */
class FarmFactory extends Factory
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
            'name' => $this->faker->company() . ' Farm',
            'location' => $this->faker->city(),
            'size' => $this->faker->randomFloat(2, 10, 1000),
            'coordinates' => 'POINT(' . $this->faker->longitude() . ' ' . $this->faker->latitude() . ')',
            'description' => $this->faker->paragraph(),
        ];
    }
}
