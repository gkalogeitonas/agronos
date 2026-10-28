<?php

use App\Enums\DeviceStatus;
use App\Jobs\ProcessSensorInfluxData;
use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

it('does not send LoRa devices through the device API', function () {
    $device = Device::factory()->lora()->create();
    Sensor::factory()->create(['device_id' => $device->id]);
    Http::preventStrayRequests();
    Http::fake();

    $this->artisan('devices:simulate', ['--via' => 'http', '--once' => true])
        ->expectsOutputToContain('No devices with sensors found to simulate.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('rejects an unsupported transport', function () {
    Sensor::factory()->create();

    $this->artisan('devices:simulate', ['--via' => 'carrier-pigeon', '--once' => true])
        ->expectsOutputToContain('Unsupported --via value "carrier-pigeon"')
        ->assertFailed();
});
