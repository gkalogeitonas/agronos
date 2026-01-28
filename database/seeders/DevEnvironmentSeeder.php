<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Farm;
use App\Models\Device;
use App\Models\Sensor;

class DevEnvironmentSeeder extends Seeder
{
    /**
     * Seed the application's database with current dev environment data.
     */
    public function run(): void
    {
        $data = [
            [
                'farm' => [
                    'name' => 'Τριποδες',
                    'location' => 'Τριποδες Ναξου',
                    'size' => '5498.63',
                    'coordinates' => [
                        'type' => 'Polygon',
                        'coordinates' => [
                            [
                                [122.795943,63.594299],
                                [122.796943,63.594299],
                                [122.796943,63.595299],
                                [122.795943,63.595299],
                                [122.795943,63.594299],
                            ],
                        ],
                    ],
                    'description' => 'Alias necessitatibus cupiditate quia recusandae hic consequatur. Omnis pariatur in cum autem sunt. Cupiditate eveniet numquam qui corrupti corrupti consequatur aut.',
                ],
                'user' => [
                    'name' => 'john Doe',
                    'email' => 'johndoe@gmail.com',
                ],
                'sensors' => [
                    [
                        'sensor' => [
                            'name' => 'Test-Device-1-sensor-1',
                            'uuid' => 'Test-Device-1-sensor-1',
                            'type' => 'temperature',
                            'lat' => '37.0582268',
                            'lon' => '25.4074122',
                            'last_reading' => '20.00',
                            'last_reading_at' => '2026-01-06 21:02:57',
                        ],
                        'device' => [
                            'name' => 'Test-Device-1',
                            'uuid' => 'Test-Device-1',
                            'secret' => '$2y$12$E/8Q1PwgKwSuJw2G/VU5zOV6epZ6Vxgx7MHh9T35CIF9UvkJm8CU2',
                            'type' => 'wifi',
                            'status' => 'online',
                            'battery_level' => null,
                            'signal_strength' => null,
                            'mqtt_username' => 'Test-Device-1',
                            'mqtt_password' => 'ac843d75e2711626d9d3c18f3ae370dd',
                        ],
                    ],
                    [
                        'sensor' => [
                            'name' => 'Test-Device-1-sensor-2',
                            'uuid' => 'Test-Device-1-sensor-2',
                            'type' => 'humidity',
                            'lat' => '37.0582268',
                            'lon' => '25.4074122',
                            'last_reading' => '20.90',
                            'last_reading_at' => '2026-01-06 21:02:57',
                        ],
                        'device' => [
                            'name' => 'Test-Device-1',
                            'uuid' => 'Test-Device-1',
                            'secret' => '$2y$12$E/8Q1PwgKwSuJw2G/VU5zOV6epZ6Vxgx7MHh9T35CIF9UvkJm8CU2',
                            'type' => 'wifi',
                            'status' => 'online',
                            'battery_level' => null,
                            'signal_strength' => null,
                            'mqtt_username' => 'Test-Device-1',
                            'mqtt_password' => 'ac843d75e2711626d9d3c18f3ae370dd',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($data as $entry) {
            $userData = $entry['user'] ?? null;
            if (!$userData) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => bcrypt('password')]
            );

            $farmData = $entry['farm'] ?? [];
            $farmData['user_id'] = $user->id;
            $farm = Farm::create($farmData);

            foreach ($entry['sensors'] as $sentry) {
                $deviceData = $sentry['device'] ?? null;
                $device = null;
                if ($deviceData) {
                    $deviceData['user_id'] = $user->id;
                    $device = Device::firstOrCreate(
                        ['uuid' => $deviceData['uuid']],
                        $deviceData
                    );
                }

                $sensorData = $sentry['sensor'] ?? [];
                $sensorData['user_id'] = $user->id;
                $sensorData['farm_id'] = $farm->id;
                if ($device) {
                    $sensorData['device_id'] = $device->id;
                }

                Sensor::updateOrCreate(
                    ['uuid' => $sensorData['uuid']],
                    $sensorData
                );
            }
        }
    }
}
