<?php

namespace Database\Factories;

use App\Enums\AlertRule;
use App\Models\Alert;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule' => AlertRule::DEVICE_OFFLINE,
            'severity' => AlertRule::DEVICE_OFFLINE->severity(),
            'subject_type' => (new Device)->getMorphClass(),
            'subject_id' => Device::factory()->offline(),
            'user_id' => fn (array $attributes) => Device::allTenants()->findOrFail($attributes['subject_id'])->user_id,
            'opened_at' => now(),
        ];
    }

    /**
     * Open the alert on the given device, for that device's tenant.
     */
    public function forDevice(Device $device): static
    {
        return $this->state(['subject_id' => $device->id, 'user_id' => $device->user_id]);
    }

    public function lowBattery(): static
    {
        return $this->state(['rule' => AlertRule::LOW_BATTERY, 'severity' => AlertRule::LOW_BATTERY->severity()]);
    }

    public function resolved(): static
    {
        return $this->state(['resolved_at' => now(), 'open_marker' => null]);
    }
}
