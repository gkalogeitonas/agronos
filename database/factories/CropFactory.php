<?php

namespace Database\Factories;

use App\Models\Crop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crop>
 */
class CropFactory extends Factory
{
    /**
     * A catalogue crop, with no owner.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => $this->faker->unique()->words(2, true),
            'eppo_code' => null,
        ];
    }

    /**
     * A crop a tenant added for their own use.
     */
    public function forTenant(?User $user = null): static
    {
        return $this->state(fn () => ['user_id' => $user?->id ?? User::factory()]);
    }
}
