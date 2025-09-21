<?php

use App\Jobs\ProcessSensorInfluxData;
use Illuminate\Support\Facades\Queue;

test('dispatches job to write sensor data to InfluxDB', function () {
    Queue::fake();

    $user = \App\Models\User::factory()->create();
    $device = \App\Models\Device::factory()->create(['user_id' => $user->id]);
    $farm = \App\Models\Farm::factory()->create(['user_id' => $user->id]);
    $sensor = \App\Models\Sensor::factory()->create([
        'uuid' => 'sensor-uuid-1',
        'user_id' => $user->id,
        'device_id' => $device->id,
        'farm_id' => $farm->id,
        'type' => 'temperature',
    ]);
    $token = $device->createToken('device-token')->plainTextToken;

    $payload = [
        'sensors' => [
            ['uuid' => 'sensor-uuid-1', 'value' => 22.5],
        ],
    ];

    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', $payload);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Data received.']);

    // Assert that the job was dispatched
    Queue::assertPushed(ProcessSensorInfluxData::class, function ($job) use ($sensor) {
        return $job->sensor->id === $sensor->id
            && $job->value === 22.5;
    });

    // Assert it was pushed to the correct queue
    Queue::assertPushedOn('sensor-data', ProcessSensorInfluxData::class);
});

test('job writes correct data to InfluxDBFake when processed', function () {
    $user = \App\Models\User::factory()->create();
    $device = \App\Models\Device::factory()->create(['user_id' => $user->id]);
    $farm = \App\Models\Farm::factory()->create(['user_id' => $user->id]);
    $sensor = \App\Models\Sensor::factory()->create([
        'uuid' => 'sensor-uuid-1',
        'user_id' => $user->id,
        'device_id' => $device->id,
        'farm_id' => $farm->id,
        'type' => 'temperature',
    ]);

    // Create and process the job directly
    $job = new ProcessSensorInfluxData($sensor, 22.5, time());
    $job->handle(app(\App\Services\InfluxDBService::class));

    $influx = app(\App\Services\InfluxDBService::class);
    expect($influx)->toBeInstanceOf(\App\Services\InfluxDBFake::class);
    /** @var \App\Services\InfluxDBFake $influx */
    $writes = $influx->writes();
    expect($writes)->toHaveCount(1);
    expect($writes[0]['tags'])->toMatchArray([
        'user_id' => $user->id,
        'farm_id' => $farm->id,
        'sensor_id' => $sensor->id,
        'sensor_type' => 'temperature',
    ]);
    expect($writes[0]['fields'])->toMatchArray(['value' => 22.5]);
});
