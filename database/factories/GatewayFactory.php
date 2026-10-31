<?php

namespace Database\Factories;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gateway>
 */
class GatewayFactory extends Factory
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
            'name' => $this->faker->word().' Gateway',
            'mqtt_username' => 'gw-'.$this->faker->uuid(),
            'mqtt_password' => bin2hex(random_bytes(16)),
        ];
    }
}
