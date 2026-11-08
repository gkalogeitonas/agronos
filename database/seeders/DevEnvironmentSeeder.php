<?php

namespace Database\Seeders;

use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevEnvironmentSeeder extends Seeder
{
    /**
     * Plaintext secret of the seeded devices, used by `devices:simulate --via=http|mqtt` to log in.
     * It is public, so this seeder only runs in local and testing environments.
     */
    public const DEVICE_SECRET = 'secret';

    /**
     * Name of the seeded farm with many devices, for exercising farm screens at scale.
     */
    public const LARGE_FARM_NAME = 'Λιβάδι Δρυμαλίας';

    /**
     * How many devices the large farm gets.
     */
    private const LARGE_FARM_DEVICE_COUNT = 50;

    /**
     * The large farm's boundary as [west, south, east, north]; devices sit on a grid inside it.
     *
     * @var array{float, float, float, float}
     */
    private const LARGE_FARM_BOUNDS = [25.4300, 37.0700, 25.4400, 37.0760];

    /**
     * The large farm's zones that hold the reading sensors of a device grid row, first row first.
     * Devices in the rows past them keep their sensors out of any zone.
     *
     * @var list<string>
     */
    private const LARGE_FARM_ROW_ZONES = ['Σειρά 1', 'Σειρά 2', 'Σειρά 3'];

    /**
     * A zone of the large farm that holds no sensors.
     */
    private const LARGE_FARM_EMPTY_ZONE = 'Νέα φύτευση';

    /**
     * Seed the application's database with current dev environment data.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping DevEnvironmentSeeder: its devices use a public secret, so it only runs in local and testing.');

            return;
        }

        $deviceSecret = Hash::make(self::DEVICE_SECRET);

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
                                [122.795943, 63.594299],
                                [122.796943, 63.594299],
                                [122.796943, 63.595299],
                                [122.795943, 63.595299],
                                [122.795943, 63.594299],
                            ],
                        ],
                    ],
                    'description' => 'Alias necessitatibus cupiditate quia recusandae hic consequatur. Omnis pariatur in cum autem sunt. Cupiditate eveniet numquam qui corrupti corrupti consequatur aut.',
                ],
                'zones' => ['Βόρειος αμπελώνας', 'Νότιος αμπελώνας'],
                'user' => [
                    'name' => 'john Doe',
                    'email' => 'johndoe@gmail.com',
                ],
                'sensors' => [
                    [
                        'zone' => 'Βόρειος αμπελώνας',
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
                            'secret' => $deviceSecret,
                            'type' => 'wifi',
                            'status' => 'online',
                            'battery_level' => null,
                            'signal_strength' => null,
                            'mqtt_username' => 'Test-Device-1',
                            'mqtt_password' => 'ac843d75e2711626d9d3c18f3ae370dd',
                        ],
                    ],
                    [
                        'zone' => 'Βόρειος αμπελώνας',
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
                            'secret' => $deviceSecret,
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
                            'name' => 'Test-Device-1-battery',
                            'uuid' => 'Test-Device-1-battery',
                            'type' => 'battery',
                            'lat' => '37.0582268',
                            'lon' => '25.4074122',
                            'last_reading' => '100.00',
                            'last_reading_at' => '2026-01-06 21:02:57',
                        ],
                        'device' => [
                            'name' => 'Test-Device-1',
                            'uuid' => 'Test-Device-1',
                            'secret' => $deviceSecret,
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
            if (! $userData) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => bcrypt('password')]
            );

            $farmData = $entry['farm'] ?? [];
            $farm = Farm::allTenants()->updateOrCreate(
                ['user_id' => $user->id, 'name' => $farmData['name']],
                $farmData
            );

            $zones = $this->seedZones($farm, $entry['zones'] ?? []);

            foreach ($entry['sensors'] as $sentry) {
                $deviceData = $sentry['device'] ?? null;
                $device = null;
                if ($deviceData) {
                    $deviceData['user_id'] = $user->id;
                    $deviceData['farm_id'] = $farm->id;
                    $device = Device::allTenants()->updateOrCreate(
                        ['uuid' => $deviceData['uuid']],
                        $deviceData
                    );
                }

                $sensorData = $sentry['sensor'] ?? [];
                $sensorData['user_id'] = $user->id;
                $sensorData['zone_id'] = isset($sentry['zone']) ? $zones[$sentry['zone']]->id : null;
                if ($device) {
                    $sensorData['device_id'] = $device->id;
                }

                Sensor::allTenants()->updateOrCreate(
                    ['uuid' => $sensorData['uuid']],
                    $sensorData
                );
            }
        }

        $this->seedLargeFarm($deviceSecret);
    }

    /**
     * Create the farm's zones, keyed by farm and name so re-seeding updates them in place.
     *
     * @param  list<string>  $names
     * @return array<string, Zone>
     */
    private function seedZones(Farm $farm, array $names): array
    {
        $zones = [];

        foreach ($names as $name) {
            $zones[$name] = Zone::allTenants()->updateOrCreate(
                ['farm_id' => $farm->id, 'name' => $name],
                ['user_id' => $farm->user_id]
            );
        }

        return $zones;
    }

    /**
     * A farm with many devices. Each device has one or two reading sensors and a battery sensor.
     * The reading sensors of the first grid rows are in a zone per row; battery sensors are never in a zone.
     * Devices and sensors are keyed by UUID and the farm and zones by name, so re-seeding updates them in place.
     */
    private function seedLargeFarm(string $deviceSecret): void
    {
        $user = User::firstOrCreate(
            ['email' => 'johndoe@gmail.com'],
            ['name' => 'john Doe', 'password' => bcrypt('password')]
        );

        [$west, $south, $east, $north] = self::LARGE_FARM_BOUNDS;

        $farm = Farm::allTenants()->updateOrCreate(
            ['user_id' => $user->id, 'name' => self::LARGE_FARM_NAME],
            [
                'location' => 'Δρυμαλία Νάξου',
                'size' => '488000.00',
                'coordinates' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]]],
                ],
                'description' => 'Large demo farm for checking farm screens with many devices and sensors.',
            ]
        );

        $rowZones = array_values($this->seedZones($farm, [...self::LARGE_FARM_ROW_ZONES, self::LARGE_FARM_EMPTY_ZONE]));

        $columns = 10;
        $rows = (int) ceil(self::LARGE_FARM_DEVICE_COUNT / $columns);

        for ($index = 0; $index < self::LARGE_FARM_DEVICE_COUNT; $index++) {
            $number = sprintf('%02d', $index + 1);
            $uuid = "Drymalia-Device-{$number}";
            $row = intdiv($index, $columns);
            $lon = round($west + ($east - $west) * (($index % $columns) + 0.5) / $columns, 7);
            $lat = round($south + ($north - $south) * ($row + 0.5) / $rows, 7);
            $batteryLevel = 100 - ($index * 7) % 95;
            $zoneId = $row < count(self::LARGE_FARM_ROW_ZONES) ? $rowZones[$row]->id : null;

            $device = Device::allTenants()->updateOrCreate(
                ['uuid' => $uuid],
                [
                    'user_id' => $user->id,
                    'farm_id' => $farm->id,
                    'name' => $uuid,
                    'secret' => $deviceSecret,
                    'type' => 'wifi',
                    'status' => $index % 6 === 5 ? 'offline' : 'online',
                    'battery_level' => $batteryLevel,
                    'signal_strength' => 1 + $index % 5,
                    'last_seen_at' => now(),
                ]
            );

            $sensors = [
                ['suffix' => 'moisture', 'type' => 'moisture', 'last_reading' => 25 + ($index * 3) % 30],
                ...($index % 2 === 1 ? [['suffix' => 'temperature', 'type' => 'temperature', 'last_reading' => 16 + ($index * 5) % 12]] : []),
                ['suffix' => 'battery', 'type' => 'battery', 'last_reading' => $batteryLevel],
            ];

            foreach ($sensors as $sensor) {
                Sensor::allTenants()->updateOrCreate(
                    ['uuid' => "{$uuid}-{$sensor['suffix']}"],
                    [
                        'user_id' => $user->id,
                        'device_id' => $device->id,
                        'name' => "{$uuid}-{$sensor['suffix']}",
                        'type' => $sensor['type'],
                        'zone_id' => $sensor['type'] === SensorType::BATTERY->value ? null : $zoneId,
                        'lat' => $lat,
                        'lon' => $lon,
                        'last_reading' => $sensor['last_reading'],
                        'last_reading_at' => now(),
                    ]
                );
            }
        }
    }
}
