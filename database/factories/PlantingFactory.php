<?php

namespace Database\Factories;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Planting;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A whole-farm planting of a catalogue crop, with no dates, so it is current.
 *
 * @extends Factory<Planting>
 */
class PlantingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'user_id' => fn (array $attributes) => Farm::allTenants()->findOrFail($attributes['farm_id'])->user_id,
            'zone_id' => null,
            'crop_id' => Crop::factory(),
            'variety' => null,
            'started_on' => null,
            'ended_on' => null,
        ];
    }

    /**
     * A planting of one zone of its farm.
     */
    public function forZone(?Zone $zone = null): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => $zone?->id ?? Zone::factory(['farm_id' => $attributes['farm_id']]),
        ]);
    }

    /**
     * A planting of a crop the farm owner added.
     */
    public function ofTenantCrop(): static
    {
        return $this->state(fn (array $attributes) => [
            'crop_id' => Crop::factory()->forTenant(Farm::allTenants()->findOrFail($attributes['farm_id'])->user),
        ]);
    }

    /**
     * A planting that ended before today.
     */
    public function ended(): static
    {
        return $this->state(fn () => [
            'started_on' => now()->subYears(2)->toDateString(),
            'ended_on' => now()->subYear()->toDateString(),
        ]);
    }

    /**
     * A planting that starts after today.
     */
    public function future(): static
    {
        return $this->state(fn () => ['started_on' => now()->addMonth()->toDateString()]);
    }
}
