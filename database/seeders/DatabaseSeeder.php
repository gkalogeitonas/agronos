<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create the test user
        $user = User::factory()->create([
            'name' => 'john Doe',
            'email' => 'johndoe@gmail.com',
            'password' => bcrypt('password'), // Use bcrypt for password hashing
        ]);

        // Create 2 farms for the test user, each with 1 device, each device with 3 sensors
        Farm::factory(2)->create(['user_id' => $user->id])->each(function ($farm) use ($user) {
            $devices = \App\Models\Device::factory(1)->create([
                'user_id' => $user->id,
            ]);
            foreach ($devices as $device) {
                \App\Models\Sensor::factory(3)->create([
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'farm_id' => $farm->id,
                ]);
            }
        });
    }
}
