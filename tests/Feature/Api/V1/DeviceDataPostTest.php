<?php

use Illuminate\Support\Facades\Log;
use App\Models\Device;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use App\Enums\DeviceStatus;
use Carbon\Carbon;

it('allows a device to post data with a valid token', function () {
    $device = Device::factory()->create();
    $token = $device->createToken('device-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', [
            'sensors' => [
                ['uuid' => 'sensor-uuid-1', 'value' => 25.5],
                ['uuid' => 'sensor-uuid-2', 'value' => 60],
            ],
        ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Data received.']);
});

it('rejects a device posting data with an invalid token', function () {
    $response = $this->withToken('invalid-token')
        ->postJson('/api/v1/device/data', [
            'sensors' => [
                ['uuid' => 'sensor-uuid-1', 'value' => 25.5],
                ['uuid' => 'sensor-uuid-2', 'value' => 60],
            ],
        ]);

    $response->assertStatus(401);
});

it('rejects a device posting data with no token', function () {
    $response = $this->postJson('/api/v1/device/data', [
        'sensors' => [
            ['uuid' => 'sensor-uuid-1', 'value' => 25.5],
            ['uuid' => 'sensor-uuid-2', 'value' => 60],
        ],
    ]);

    $response->assertStatus(401);
});

it('device can login and then post data with the received token', function () {
    // Create device with known credentials
    $device = Device::factory()->create([
        'uuid' => 'end-to-end-device-uuid',
        'secret' => Hash::make('end-to-end-secret'),
    ]);

    // Device logs in to get token
    $loginResponse = $this->postJson('/api/v1/device/login', [
        'uuid' => 'end-to-end-device-uuid',
        'secret' => 'end-to-end-secret',
    ]);

    $loginResponse->assertStatus(200)->assertJsonStructure(['token']);
    $token = $loginResponse->json('token');

    // Device uses token to post data
    $dataResponse = $this->withToken($token)
        ->postJson('/api/v1/device/data', [
            'sensors' => [
                ['uuid' => 'sensor-uuid-1', 'value' => 22.3],
                ['uuid' => 'sensor-uuid-2', 'value' => 55],
            ],
        ]);

    $dataResponse->assertStatus(200)
        ->assertJsonFragment(['message' => 'Data received.']);
});

it('rejects a device posting data with invalid payload', function () {
    $device = Device::factory()->create();
    $token = $device->createToken('device-token')->plainTextToken;

    // Missing 'sensors' key
    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', [
            'temperature' => 25.5,
        ]);
    $response->assertStatus(422);

    // Invalid sensors array (missing uuid)
    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', [
            'sensors' => [
                ['value' => 25.5],
            ],
        ]);
    $response->assertStatus(422);

    // Invalid sensors array (missing value)
    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', [
            'sensors' => [
                ['uuid' => 'sensor-uuid-1'],
            ],
        ]);
    $response->assertStatus(422);
});

it('skips writing data for sensors that are not registered and returns missing_uuids', function () {
    $user = \App\Models\User::factory()->create();
    $device = \App\Models\Device::factory()->create(['user_id' => $user->id, 'id' => 99]);
    $farm = \App\Models\Farm::factory()->create(['user_id' => $user->id]);

    $registeredSensor = \App\Models\Sensor::factory()->create([
        'uuid' => 'sensor-uuid-registered',
        'user_id' => $user->id,
        'device_id' => $device->id,
        'farm_id' => $farm->id,
        'type' => 'temperature',
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    $payload = [
        'sensors' => [
            ['uuid' => 'sensor-uuid-registered', 'value' => 22.5],
            ['uuid' => 'sensor-uuid-unregistered', 'value' => 99.9], // not registered
        ],
    ];

    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Data received.',
            'missing_uuids' => ['sensor-uuid-unregistered'],
        ]);

    $influx = app(\App\Services\InfluxDBService::class);
    expect($influx)->toBeInstanceOf(\App\Services\InfluxDBFake::class);
    /** @var \App\Services\InfluxDBFake $influx */
    $writes = $influx->writes();
    expect($writes)->toHaveCount(1);
    expect($writes[0]['tags']['sensor_id'])->toBe($registeredSensor->id);
    expect($writes[0]['fields']['value'])->toBe(22.5);
});

it('does not write data for sensors not belonging to the device', function () {
    $user = \App\Models\User::factory()->create();
    $device = \App\Models\Device::factory()->create(['user_id' => $user->id]);
    $otherDevice = \App\Models\Device::factory()->create(['user_id' => $user->id]);
    $farm = \App\Models\Farm::factory()->create(['user_id' => $user->id]);

    // Sensor belongs to otherDevice, not to $device
    $sensor = \App\Models\Sensor::factory()->create([
        'uuid' => 'sensor-uuid-other',
        'user_id' => $user->id,
        'device_id' => $otherDevice->id,
        'farm_id' => $farm->id,
        'type' => 'temperature',
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    $payload = [
        'sensors' => [
            ['uuid' => 'sensor-uuid-other', 'value' => 22.5],
        ],
    ];

    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', $payload);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Data received.']);
    $influx = app(\App\Services\InfluxDBService::class);
    expect($influx)->toBeInstanceOf(\App\Services\InfluxDBFake::class);
    /** @var \App\Services\InfluxDBFake $influx */
    $writes = $influx->writes();
    expect($writes)->toHaveCount(0);
});

it('does not write data for sensors not belonging to the user who owns the device', function () {
    $user = \App\Models\User::factory()->create();
    $otherUser = \App\Models\User::factory()->create();
    $device = \App\Models\Device::factory()->create(['user_id' => $user->id]);
    $otherDevice = \App\Models\Device::factory()->create(['user_id' => $otherUser->id]);
    $farm = \App\Models\Farm::factory()->create(['user_id' => $otherUser->id]);

    // Sensor belongs to otherUser and otherDevice
    $sensor = \App\Models\Sensor::factory()->create([
        'uuid' => 'sensor-uuid-other-user',
        'user_id' => $otherUser->id,
        'device_id' => $otherDevice->id,
        'farm_id' => $farm->id,
        'type' => 'humidity',
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    $payload = [
        'sensors' => [
            ['uuid' => 'sensor-uuid-other-user', 'value' => 44.4],
        ],
    ];

    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', $payload);


    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Data received.'])
        ->assertJsonFragment(['missing_uuids' => ['sensor-uuid-other-user']]);
    $influx = app(\App\Services\InfluxDBService::class);
    expect($influx)->toBeInstanceOf(\App\Services\InfluxDBFake::class);
    /** @var \App\Services\InfluxDBFake $influx */
    $writes = $influx->writes();
    expect($writes)->toHaveCount(0);
});

it('updates last_reading and last_reading_at on sensors after posting data', function () {
    $device = \App\Models\Device::factory()->create();
    $sensor = \App\Models\Sensor::factory()->create([
        'device_id' => $device->id,
        'uuid' => 'sensor-uuid-1',
        'last_reading' => null,
        'last_reading_at' => null,
    ]);
    $token = $device->createToken('device-token')->plainTextToken;


    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', [
            'sensors' => [
                ['uuid' => 'sensor-uuid-1', 'value' => 42.42],
            ],
        ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Data received.']);

    $sensor->refresh();
    expect($sensor->last_reading)->toBe(42.42);
    expect($sensor->last_reading_at)->not->toBeNull();
});


it('updates device status to ONLINE and last_seen_at when device sends data', function () {
    $device = Device::factory()->create([
        'uuid' => 'test-uuid-data',
        'secret' => Hash::make('test-secret'),
        'status' => DeviceStatus::REGISTERED,
        'last_seen_at' => now()->subHour(),
    ]);

    // Simulate device authentication
    $token = $device->createToken('device-token')->plainTextToken;

    $payload = [
        'sensors' => [
            [
                'uuid' => 'sensor-uuid-1',
                'value' => 22.5,
            ],
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/v1/device/data', $payload);

    $response->assertStatus(200);

    $device->refresh();
    expect($device->status)->toBe(DeviceStatus::ONLINE);
    expect($device->last_seen_at)->not->toBeNull();
    // Parse last_seen_at as Carbon for comparison
    $lastSeen = Carbon::parse($device->last_seen_at);
    expect($lastSeen->greaterThan(now()->subMinute()))->toBeTrue();
});
