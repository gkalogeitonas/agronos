<?php

use App\Jobs\ProcessSensorInfluxData;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use App\Services\InfluxDBFake;
use App\Services\InfluxDBService;
use Illuminate\Support\Facades\Queue;

/**
 * Post one reading for the sensor through the device API.
 */
function postReading(Device $device, Sensor $sensor, float $value = 22.5): void
{
    test()->withToken($device->createToken('device-token')->plainTextToken)
        ->postJson('/api/v1/device/data', ['sensors' => [['uuid' => $sensor->uuid, 'value' => $value]]])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Data received.']);
}

function influxFake(): InfluxDBFake
{
    $influx = app(InfluxDBService::class);
    expect($influx)->toBeInstanceOf(InfluxDBFake::class);

    return $influx;
}

test('dispatches job to write sensor data to InfluxDB', function () {
    Queue::fake();

    $device = Device::factory()->onFarm()->create();
    $sensor = Sensor::factory()->create(['user_id' => $device->user_id, 'device_id' => $device->id, 'type' => 'temperature']);

    postReading($device, $sensor);

    Queue::assertPushed(ProcessSensorInfluxData::class, function ($job) use ($sensor) {
        return $job->sensor->id === $sensor->id
            && $job->value === 22.5;
    });
    Queue::assertPushedOn('sensor-data', ProcessSensorInfluxData::class);
});

test('a reading from a device on a farm is written with that farm as its tag', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);
    $device = Device::factory()->onFarm($farm)->create();
    $sensor = Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device->id, 'type' => 'temperature']);

    postReading($device, $sensor);

    $writes = influxFake()->writes();
    expect($writes)->toHaveCount(1);
    expect($writes[0]['tags'])->toBe([
        'user_id' => $user->id,
        'farm_id' => $farm->id,
        'sensor_id' => $sensor->id,
        'sensor_type' => 'temperature',
    ]);
    expect($writes[0]['fields'])->toMatchArray(['value' => 22.5]);
});

test('a reading from an unassigned device is written without a farm tag', function () {
    $device = Device::factory()->create();
    $sensor = Sensor::factory()->create(['user_id' => $device->user_id, 'device_id' => $device->id, 'type' => 'temperature']);

    postReading($device, $sensor);

    $writes = influxFake()->writes();
    expect($writes)->toHaveCount(1);
    expect($writes[0]['tags'])->not->toHaveKey('farm_id');
    expect($writes[0]['tags'])->toMatchArray(['user_id' => $device->user_id, 'sensor_id' => $sensor->id, 'sensor_type' => 'temperature']);
});

test('a reading keeps its farm when the device moves before the queued write runs', function () {
    Queue::fake();

    $user = User::factory()->create();
    $originalFarm = Farm::factory()->create(['user_id' => $user->id]);
    $otherFarm = Farm::factory()->create(['user_id' => $user->id]);
    $device = Device::factory()->onFarm($originalFarm)->create();
    $sensor = Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device->id, 'type' => 'temperature']);

    postReading($device, $sensor);
    $device->update(['farm_id' => $otherFarm->id]);

    $job = Queue::pushed(ProcessSensorInfluxData::class)->sole();
    // Round-trip through the queue payload so the sensor is reloaded as a real worker would.
    $job = unserialize(serialize($job));
    $job->handle(app(InfluxDBService::class));

    expect(influxFake()->writes()[0]['tags']['farm_id'])->toBe($originalFarm->id);
});
