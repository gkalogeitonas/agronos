<?php

namespace Database\Factories;

use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sensor>
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
            'user_id' => User::factory(),
            'device_id' => Device::factory(),
            'farm_id' => Farm::factory(),
            'name' => $this->faker->words(3, true),
            'uuid' => $this->faker->uuid,
            'type' => $this->faker->randomElement(SensorType::values()),
            'lat' => $this->faker->latitude,
            'lon' => $this->faker->longitude,
        ];
    }
}
