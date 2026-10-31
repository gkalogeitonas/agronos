<?php

use App\Enums\DeviceStatus;
use App\Jobs\ProcessSensorInfluxData;
use App\Models\Device;
use App\Models\Sensor;
use App\Services\DeviceMqttPublisher;
use App\Services\TimeSeries\SensorTimeSeriesService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Mockery\MockInterface;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;

it('sends one round of readings and marks the device online', function () {
    $device = Device::factory()->create(['status' => DeviceStatus::OFFLINE, 'last_seen_at' => null]);
    $sensor = Sensor::factory()->create([
        'device_id' => $device->id,
        'type' => 'temperature',
        'last_reading' => 40,
    ]);
    Queue::fake([ProcessSensorInfluxData::class]);
    $this->freezeTime();

    $this->artisan('devices:simulate', ['--once' => true])->assertSuccessful();

    $device->refresh();
    expect($device->status)->toBe(DeviceStatus::ONLINE)
        ->and($device->last_seen_at)->toBe(now()->toDateTimeString());
    expect((float) $sensor->refresh()->last_reading)->toBeBetween(39.5, 40.0);
    Queue::assertPushed(ProcessSensorInfluxData::class, fn (ProcessSensorInfluxData $job) => $job->sensor->is($sensor));
});

it('only simulates the devices given by uuid', function () {
    $selected = Device::factory()->create();
    $selectedSensor = Sensor::factory()->create(['device_id' => $selected->id, 'last_reading' => null]);
    $other = Device::factory()->create();
    $otherSensor = Sensor::factory()->create(['device_id' => $other->id, 'last_reading' => null]);
    Queue::fake([ProcessSensorInfluxData::class]);

    $this->artisan('devices:simulate', ['devices' => [$selected->uuid], '--once' => true])->assertSuccessful();

    expect($selectedSensor->refresh()->last_reading)->not->toBeNull();
    expect($otherSensor->refresh()->last_reading)->toBeNull();
});

it('fails when no device has sensors', function () {
    Device::factory()->create();

    $this->artisan('devices:simulate', ['--once' => true])
        ->expectsOutputToContain('No devices with sensors found to simulate.')
        ->assertFailed();
});

it('refuses to run in production', function () {
    $sensor = Sensor::factory()->create(['last_reading' => null]);
    Queue::fake([ProcessSensorInfluxData::class]);
    $this->app['env'] = 'production';

    $this->artisan('devices:simulate', ['--once' => true])->assertFailed();

    expect($sensor->refresh()->last_reading)->toBeNull();
    Queue::assertNotPushed(ProcessSensorInfluxData::class);
});

it('logs in through the device API and posts readings with the token', function () {
    config(['app.url' => 'https://agronos.test']);
    $device = Device::factory()->create(['uuid' => 'sim-device', 'type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $device->id, 'uuid' => 'sim-sensor', 'type' => 'humidity', 'last_reading' => 50]);
    Http::preventStrayRequests();
    Http::fake([
        'https://agronos.test/api/v1/device/login' => Http::response(['token' => 'device-token']),
        'https://agronos.test/api/v1/device/data' => Http::response(['message' => 'Data received.']),
    ]);

    $this->artisan('devices:simulate', ['--via' => 'http', '--secret' => 'dev-secret', '--once' => true])->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://agronos.test/api/v1/device/login'
        && $request['uuid'] === 'sim-device'
        && $request['secret'] === 'dev-secret');
    Http::assertSent(function (Request $request) {
        $value = $request['sensors'][0]['value'] ?? null;

        return $request->url() === 'https://agronos.test/api/v1/device/data'
            && $request->hasHeader('Authorization', 'Bearer device-token')
            && $request['sensors'][0]['uuid'] === 'sim-sensor'
            && $value >= 48 && $value <= 52;
    });
});

it('logs in again when the device API rejects the token', function () {
    config(['app.url' => 'https://agronos.test']);
    $device = Device::factory()->create(['type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $device->id]);
    Http::preventStrayRequests();
    Http::fake([
        'https://agronos.test/api/v1/device/login' => Http::sequence()
            ->push(['token' => 'expired-token'])
            ->push(['token' => 'fresh-token']),
        'https://agronos.test/api/v1/device/data' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(['message' => 'Data received.']),
    ]);

    $this->artisan('devices:simulate', ['--via' => 'http', '--once' => true])->assertSuccessful();

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://agronos.test/api/v1/device/data'
        && $request->hasHeader('Authorization', 'Bearer fresh-token'));
});

it('skips a device that cannot log in and keeps simulating the others', function () {
    config(['app.url' => 'https://agronos.test']);
    $rejected = Device::factory()->create(['uuid' => 'rejected-device', 'type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $rejected->id]);
    $accepted = Device::factory()->create(['uuid' => 'accepted-device', 'type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $accepted->id]);
    Http::preventStrayRequests();
    Http::fake([
        'https://agronos.test/api/v1/device/login' => fn (Request $request) => $request['uuid'] === 'rejected-device'
            ? Http::response(['message' => 'Invalid credentials'], 401)
            : Http::response(['token' => 'accepted-token']),
        'https://agronos.test/api/v1/device/data' => Http::response(['message' => 'Data received.']),
    ]);

    $this->artisan('devices:simulate', ['--via' => 'http', '--once' => true])
        ->expectsOutputToContain('[rejected-device] Skipped: login failed with HTTP 401')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://agronos.test/api/v1/device/data'
        && $request->hasHeader('Authorization', 'Bearer accepted-token'));
    Http::assertSentCount(3);
});

it('does not simulate LoRa devices through the device API or the broker', function (string $via) {
    $device = Device::factory()->lora()->create();
    Sensor::factory()->create(['device_id' => $device->id]);
    Http::preventStrayRequests();
    Http::fake();

    $this->artisan('devices:simulate', ['--via' => $via, '--once' => true])
        ->expectsOutputToContain('No devices with sensors found to simulate.')
        ->assertFailed();

    Http::assertNothingSent();
})->with(['http', 'mqtt']);

it('connects to the broker with the provisioned credentials and publishes readings', function () {
    config(['app.url' => 'https://agronos.test']);
    $device = Device::factory()->create(['uuid' => 'sim-device', 'type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $device->id, 'uuid' => 'sim-sensor', 'type' => 'humidity', 'last_reading' => 50]);
    Http::preventStrayRequests();
    Http::fake([
        'https://agronos.test/api/v1/device/login' => Http::response(['token' => 'device-token']),
        'https://agronos.test/api/v1/device/mqtt-credentials' => Http::response(['username' => 'sim-device', 'password' => 'mqtt-pass', 'topic_base' => 'farm-a/devices/sim-device', 'created' => false]),
    ]);
    $this->mock(DeviceMqttPublisher::class, function (MockInterface $mock) {
        $mock->shouldReceive('connect')->once()->with('sim-device-simulator', 'sim-device', 'mqtt-pass', 60);
        $mock->shouldReceive('publish')->once()->withArgs(function (string $topic, string $payload) {
            $value = json_decode($payload, true)['sensors'][0]['value'] ?? null;

            return $topic === 'farm-a/devices/sim-device/sensors'
                && json_decode($payload, true)['sensors'][0]['uuid'] === 'sim-sensor'
                && $value >= 48 && $value <= 52;
        });
        $mock->shouldReceive('disconnect')->once();
    });

    $this->artisan('devices:simulate', ['--via' => 'mqtt', '--once' => true])->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://agronos.test/api/v1/device/mqtt-credentials'
        && $request->hasHeader('Authorization', 'Bearer device-token'));
});

it('skips a device that cannot get MQTT credentials', function () {
    config(['app.url' => 'https://agronos.test']);
    $device = Device::factory()->create(['uuid' => 'sim-device', 'type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $device->id]);
    Http::preventStrayRequests();
    Http::fake([
        'https://agronos.test/api/v1/device/login' => Http::response(['token' => 'device-token']),
        'https://agronos.test/api/v1/device/mqtt-credentials' => Http::response(['created' => false, 'error' => 'emqx_unavailable'], 503),
    ]);
    $this->mock(DeviceMqttPublisher::class, fn (MockInterface $mock) => $mock->shouldNotReceive('connect'));

    $this->artisan('devices:simulate', ['--via' => 'mqtt', '--once' => true])
        ->expectsOutputToContain('[sim-device] Skipped: could not get MQTT credentials (HTTP 503: emqx_unavailable)')
        ->assertFailed();
});

it('skips a device the broker refuses to connect', function () {
    config(['app.url' => 'https://agronos.test']);
    $device = Device::factory()->create(['uuid' => 'sim-device', 'type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $device->id]);
    Http::preventStrayRequests();
    Http::fake([
        'https://agronos.test/api/v1/device/login' => Http::response(['token' => 'device-token']),
        'https://agronos.test/api/v1/device/mqtt-credentials' => Http::response(['username' => 'sim-device', 'password' => 'stale-pass', 'topic_base' => 'agronos/devices/sim-device']),
    ]);
    $this->mock(DeviceMqttPublisher::class, function (MockInterface $mock) {
        $mock->shouldReceive('connect')->once()->andThrow(new ConnectingToBrokerFailedException(5, 'not authorized'));
        $mock->shouldNotReceive('publish');
    });

    $this->artisan('devices:simulate', ['--via' => 'mqtt', '--once' => true])
        ->expectsOutputToContain('[sim-device] Skipped: MQTT connection failed')
        ->assertFailed();
});

it('rejects an unsupported transport', function () {
    Sensor::factory()->create();

    $this->artisan('devices:simulate', ['--via' => 'carrier-pigeon', '--once' => true])
        ->expectsOutputToContain('Unsupported --via value "carrier-pigeon"')
        ->assertFailed();
});

it('keeps a device when the device API cannot be reached', function () {
    config(['app.url' => 'https://agronos.test']);
    $device = Device::factory()->create(['uuid' => 'sim-device', 'type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $device->id]);
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->artisan('devices:simulate', ['--via' => 'http', '--once' => true])
        ->expectsOutputToContain('[sim-device] Could not reach the device API: Connection refused. Retrying next round.')
        ->assertSuccessful();
});

it('verifies that readings reached the database and InfluxDB', function () {
    Sleep::fake();
    $sensor = Sensor::factory()->create(['type' => 'humidity', 'last_reading' => 50]);
    Queue::fake([ProcessSensorInfluxData::class]);
    $this->mock(SensorTimeSeriesService::class, function (MockInterface $mock) use ($sensor) {
        $mock->shouldReceive('recentReadings')->andReturnUsing(fn () => [
            ['time' => now()->utc()->format('Y-m-d H:i:s'), 'value' => (float) $sensor->refresh()->last_reading],
        ]);
    });

    $this->artisan('devices:simulate', ['--verify' => true])
        ->expectsOutputToContain('OK: stored in the database and InfluxDB')
        ->assertSuccessful();
});

it('fails verification naming the stage when readings never reach Laravel', function () {
    config(['app.url' => 'https://agronos.test']);
    Sleep::fake();
    $device = Device::factory()->create(['type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $device->id, 'last_reading' => null]);
    Http::preventStrayRequests();
    Http::fake([
        'https://agronos.test/api/v1/device/login' => Http::response(['token' => 'device-token']),
        'https://agronos.test/api/v1/device/data' => Http::response(['message' => 'Data received.']),
    ]);

    $this->artisan('devices:simulate', ['--via' => 'http', '--verify' => true, '--verify-timeout' => 2])
        ->expectsOutputToContain('NOT received by Laravel')
        ->expectsOutputToContain('mqtt:setup-webhook')
        ->assertFailed();

    Sleep::assertSleptTimes(2);
});

it('fails verification naming the queue worker when readings are missing from InfluxDB', function () {
    Sleep::fake();
    Sensor::factory()->create(['type' => 'humidity', 'last_reading' => 50]);
    Queue::fake([ProcessSensorInfluxData::class]);
    $this->mock(SensorTimeSeriesService::class, fn (MockInterface $mock) => $mock->shouldReceive('recentReadings')->andReturn([]));

    $this->artisan('devices:simulate', ['--verify' => true, '--verify-timeout' => 2])
        ->expectsOutputToContain('received by Laravel, but not in InfluxDB')
        ->expectsOutputToContain('sensor-data')
        ->assertFailed();
});

it('fails verification when no reading could be sent', function () {
    config(['app.url' => 'https://agronos.test']);
    Sleep::fake();
    $device = Device::factory()->create(['type' => 'wifi']);
    Sensor::factory()->create(['device_id' => $device->id]);
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->artisan('devices:simulate', ['--via' => 'http', '--verify' => true])
        ->expectsOutputToContain('Nothing was sent')
        ->assertFailed();
});

it('does not count a reading stored before the round was sent', function () {
    config(['app.url' => 'https://agronos.test']);
    Sleep::fake();
    $device = Device::factory()->create(['type' => 'wifi']);
    $sensor = Sensor::factory()->create(['device_id' => $device->id, 'last_reading' => 50, 'last_reading_at' => now()->subHour()]);
    Http::preventStrayRequests();
    Http::fake([
        'https://agronos.test/api/v1/device/login' => Http::response(['token' => 'device-token']),
        'https://agronos.test/api/v1/device/data' => Http::response(['message' => 'Data received.']),
    ]);
    $this->mock(SensorTimeSeriesService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('recentReadings'));

    $this->artisan('devices:simulate', ['--via' => 'http', '--verify' => true, '--verify-timeout' => 1])
        ->expectsOutputToContain('NOT received by Laravel')
        ->assertFailed();
});
