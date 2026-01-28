<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\SensorType;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sensor>
 */
class SensorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'device_id' => \App\Models\Device::factory(),
            'farm_id' => \App\Models\Farm::factory(),
            'name' => $this->faker->words(3, true),
            'uuid' => $this->faker->uuid,
            'type' => $this->faker->randomElement(SensorType::values()),
            'lat' => $this->faker->latitude,
            'lon' => $this->faker->longitude,
        ];
    }
}
