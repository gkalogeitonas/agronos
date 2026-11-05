<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
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
     * A healthy device that reported just now.
     */
    public function online(): static
    {
        return $this->state(['status' => DeviceStatus::ONLINE, 'last_seen_at' => now(), 'battery_level' => 100]);
    }

    /**
     * A device with a full battery that stopped reporting a while ago.
     */
    public function offline(): static
    {
        return $this->state(['status' => DeviceStatus::OFFLINE, 'last_seen_at' => now()->subDay(), 'battery_level' => 100]);
    }

    /**
     * A device whose latest battery reading is nearly empty.
     */
    public function lowBattery(): static
    {
        return $this->state(['battery_level' => 5]);
    }

    /**
     * Place the device on the given farm, or on a new farm of the device's tenant.
     */
    public function onFarm(?Farm $farm = null): static
    {
        if ($farm) {
            return $this->state(['user_id' => $farm->user_id, 'farm_id' => $farm->id]);
        }

        // Resolved after create() overrides, so the farm belongs to the device's final tenant.
        return $this->state([
            'farm_id' => fn (array $attributes) => Farm::factory()->create(['user_id' => $attributes['user_id']])->id,
        ]);
    }
}
