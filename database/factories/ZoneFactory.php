<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'user_id' => fn (array $attributes) => Farm::allTenants()->findOrFail($attributes['farm_id'])->user_id,
            'name' => $this->faker->unique()->words(2, true),
        ];
    }
}
