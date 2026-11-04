<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
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
            'type' => $this->faker->randomElement(DeviceType::values()),
            'status' => $this->faker->randomElement(['registered', 'online', 'offline', 'error']),
            'last_seen_at' => $this->faker->optional()->dateTime(),
            'battery_level' => $this->faker->optional()->numberBetween(0, 100),
            'signal_strength' => $this->faker->optional()->numberBetween(0, 5),
            'lora_frame_counter' => 0,
        ];
    }

    public function lora(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DeviceType::LORA->value,
            'lora_aes_key' => bin2hex(random_bytes(16)),
        ]);
    }

    /**
     * Place the device on the given farm, or on a new farm of the device's tenant.
     */
    public function onFarm(?Farm $farm = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $farm?->user_id ?? $attributes['user_id'],
            'farm_id' => $farm?->id ?? Farm::factory()->state(['user_id' => $attributes['user_id']]),
        ]);
    }
}
