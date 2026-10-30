<?php

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    config(['agronos.offline_after_minutes' => 60]);
});

it('marks online devices offline once they are past the threshold', function () {
    $stale = Device::factory()->create(['status' => DeviceStatus::ONLINE, 'last_seen_at' => now()->subMinutes(61)]);
    $fresh = Device::factory()->create(['status' => DeviceStatus::ONLINE, 'last_seen_at' => now()->subMinutes(59)]);

    $this->artisan('devices:mark-offline')->expectsOutput('Marked 1 device(s) offline.')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(DeviceStatus::OFFLINE)
        ->and($fresh->fresh()->status)->toBe(DeviceStatus::ONLINE);
});

it('never uses a threshold of five minutes or less', function (int $configured) {
    config(['agronos.offline_after_minutes' => $configured]);
    $device = Device::factory()->create(['status' => DeviceStatus::ONLINE, 'last_seen_at' => now()->subMinutes(5)]);

    $this->artisan('devices:mark-offline')->assertSuccessful();

    expect($device->fresh()->status)->toBe(DeviceStatus::ONLINE);
})->with([0, -10, 5]);

it('marks online devices that never recorded a last seen time offline', function () {
    $device = Device::factory()->create(['status' => DeviceStatus::ONLINE, 'last_seen_at' => null]);

    $this->artisan('devices:mark-offline')->assertSuccessful();

    expect($device->fresh()->status)->toBe(DeviceStatus::OFFLINE);
});

it('leaves registered and error devices alone', function () {
    $registered = Device::factory()->create(['status' => DeviceStatus::REGISTERED, 'last_seen_at' => null]);
    $errored = Device::factory()->create(['status' => DeviceStatus::ERROR, 'last_seen_at' => now()->subDays(2)]);

    $this->artisan('devices:mark-offline')->assertSuccessful();

    expect($registered->fresh()->status)->toBe(DeviceStatus::REGISTERED)
        ->and($errored->fresh()->status)->toBe(DeviceStatus::ERROR);
});

it('sweeps the devices of every tenant', function () {
    $devices = Device::factory()->count(2)->create(['status' => DeviceStatus::ONLINE, 'last_seen_at' => now()->subDay()]);

    $this->artisan('devices:mark-offline')->assertSuccessful();

    $devices->each(fn (Device $device) => expect($device->fresh()->status)->toBe(DeviceStatus::OFFLINE));
});

it('brings an offline device back online with its next reading', function () {
    $device = Device::factory()->create(['status' => DeviceStatus::ONLINE, 'last_seen_at' => now()->subDay()]);
    $this->artisan('devices:mark-offline')->assertSuccessful();

    $device->fresh()->update(['status' => DeviceStatus::ONLINE, 'last_seen_at' => now()]);
    $this->artisan('devices:mark-offline')->assertSuccessful();

    expect($device->fresh()->status)->toBe(DeviceStatus::ONLINE);
});

it('is scheduled every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'devices:mark-offline'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/5 * * * *');
});
