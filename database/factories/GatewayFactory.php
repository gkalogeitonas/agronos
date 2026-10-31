<?php

namespace Database\Factories;

use App\Models\Gateway;
use App\Models\User;
use App\Services\MqttCredentialService;
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
            'mqtt_username' => Gateway::generateUsername(),
            'mqtt_password' => MqttCredentialService::generatePassword(),
        ];
    }
}
