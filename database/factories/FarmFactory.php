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
        // Generate a simple square polygon near a random point
        $centerLng = $this->faker->longitude;
        $centerLat = $this->faker->latitude;
        $delta = 0.001; // ~100m

        $polygon = [
            [
                [$centerLng, $centerLat],
                [$centerLng + $delta, $centerLat],
                [$centerLng + $delta, $centerLat + $delta],
                [$centerLng, $centerLat + $delta],
                [$centerLng, $centerLat], // Close the polygon
            ],
        ];

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->company().' Farm',
            'location' => $this->faker->city(),
            'size' => $this->faker->randomFloat(2, 10, 1000),
            'coordinates' => [
                'type' => 'Polygon',
                'coordinates' => $polygon,
            ],
            'description' => $this->faker->paragraph(),
        ];
    }
}
