<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
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
            'name' => $this->faker->word().' Device',
            'uuid' => $this->faker->uuid(),
            'secret' => $this->faker->sha256(),
            'type' => $this->faker->randomElement(['wifi', 'lora', 'other']),
            'status' => $this->faker->randomElement(['pending','registered', 'online', 'offline', 'error']),
            'last_seen_at' => $this->faker->optional()->dateTime(),
            'battery_level' => $this->faker->optional()->numberBetween(0, 100),
            'signal_strength' => $this->faker->optional()->numberBetween(0, 5),
        ];
    }
}
