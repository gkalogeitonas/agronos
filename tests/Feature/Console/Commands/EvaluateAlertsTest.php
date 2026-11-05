<?php

use App\Enums\AlertRule;
use App\Enums\AlertSeverity;
use App\Enums\DeviceStatus;
use App\Models\Alert;
use App\Models\Device;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;

it('opens one danger alert for an offline device', function () {
    $device = Device::factory()->offline()->create();

    $this->artisan('alerts:evaluate')->assertSuccessful();

    $alert = Alert::sole();
    expect($alert->rule)->toBe(AlertRule::DEVICE_OFFLINE)
        ->and($alert->severity)->toBe(AlertSeverity::DANGER)
        ->and($alert->subject->is($device))->toBeTrue()
        ->and($alert->user_id)->toBe($device->user_id)
        ->and($alert->resolved_at)->toBeNull();
});

it('creates no duplicate when it runs again', function () {
    Device::factory()->offline()->create();

    $this->artisan('alerts:evaluate')->assertSuccessful();
    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::count())->toBe(1);
});

it('rejects a second open alert for the same rule and subject at database level', function () {
    $device = Device::factory()->offline()->create();
    Alert::factory()->forDevice($device)->create();

    Alert::factory()->forDevice($device)->create();
})->throws(UniqueConstraintViolationException::class);

it('opens an alert for an unassigned device but not for a healthy one', function () {
    $unassigned = Device::factory()->offline()->create(['farm_id' => null]);
    Device::factory()->online()->onFarm()->create();

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::sole()->subject_id)->toBe($unassigned->id);
});

it('stamps an alert on an existing offline device with the detection time', function () {
    $this->travelTo('2026-11-05 12:00:00');
    Device::factory()->offline()->create(['last_seen_at' => '2026-10-01 08:00:00']);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::sole()->opened_at->toDateTimeString())->toBe('2026-11-05 12:00:00');
});

it('resolves the alert once the device is back online', function () {
    $device = Device::factory()->offline()->create();
    $this->artisan('alerts:evaluate')->assertSuccessful();

    $this->travelTo('2026-11-05 12:05:00');
    $device->update(['status' => DeviceStatus::ONLINE]);
    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::sole()->resolved_at->toDateTimeString())->toBe('2026-11-05 12:05:00');
});

it('opens a new alert on a recurrence and keeps the resolved one', function () {
    $device = Device::factory()->offline()->create();
    $this->artisan('alerts:evaluate')->assertSuccessful();
    $device->update(['status' => DeviceStatus::ONLINE]);
    $this->artisan('alerts:evaluate')->assertSuccessful();

    $device->update(['status' => DeviceStatus::OFFLINE]);
    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::whereNotNull('resolved_at')->count())->toBe(1)
        ->and(Alert::whereNull('resolved_at')->count())->toBe(1);
});

it('clears alerts whose subject is gone and leaves other alerts alone', function () {
    $deletedDevice = Device::factory()->offline()->create();
    $openOrphan = Alert::factory()->forDevice($deletedDevice)->create();
    $resolvedOrphan = Alert::factory()->forDevice($deletedDevice)->resolved()->create();
    Device::allTenants()->whereKey($deletedDevice->id)->toBase()->delete();
    $kept = Alert::factory()->create();

    $this->artisan('alerts:evaluate')->assertSuccessful();

    $this->assertModelMissing($openOrphan);
    $this->assertModelMissing($resolvedOrphan);
    $this->assertModelExists($kept);
});

it('is scheduled every five minutes after the offline sweep', function () {
    $commands = collect(app(Schedule::class)->events())->pluck('command');
    $sweep = $commands->search(fn (string $command) => str_contains($command, 'devices:mark-offline'));
    $evaluation = $commands->search(fn (string $command) => str_contains($command, 'alerts:evaluate'));

    expect($evaluation)->toBeGreaterThan($sweep)
        ->and(app(Schedule::class)->events()[$evaluation]->expression)->toBe('*/5 * * * *');
});

it('treats the alerts of other tenants the same when it runs with a signed-in user', function () {
    $kept = Alert::factory()->create();
    $cleared = Alert::factory()->forDevice(Device::factory()->online()->create())->create();
    $this->actingAs(User::factory()->create());

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect($kept->fresh()->resolved_at)->toBeNull()
        ->and($cleared->fresh()->resolved_at)->not->toBeNull();
});

it('opens one warning alert for a device with a low battery and no duplicate on a second run', function () {
    $device = Device::factory()->online()->lowBattery()->create();

    $this->artisan('alerts:evaluate')->assertSuccessful();
    $this->artisan('alerts:evaluate')->assertSuccessful();

    $alert = Alert::sole();
    expect($alert->rule)->toBe(AlertRule::LOW_BATTERY)
        ->and($alert->severity)->toBe(AlertSeverity::WARNING)
        ->and($alert->subject->is($device))->toBeTrue()
        ->and($alert->user_id)->toBe($device->user_id)
        ->and($alert->resolved_at)->toBeNull();
});

it('opens a low battery alert only below the threshold', function (int $batteryLevel, int $expectedAlerts) {
    config(['agronos.low_battery_percent' => 20]);
    Device::factory()->online()->create(['battery_level' => $batteryLevel]);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::count())->toBe($expectedAlerts);
})->with([
    'just below' => [19, 1],
    'at the threshold' => [20, 0],
]);

it('opens no alert for a device with no battery reading', function () {
    Device::factory()->online()->create(['battery_level' => null]);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::count())->toBe(0);
});

it('resolves the low battery alert as soon as the level is back at the threshold', function () {
    config(['agronos.low_battery_percent' => 20]);
    $device = Device::factory()->online()->create(['battery_level' => 19]);
    $this->artisan('alerts:evaluate')->assertSuccessful();
    $this->travelTo('2026-11-05 12:05:00');
    $device->update(['battery_level' => 20]);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::sole()->resolved_at->toDateTimeString())->toBe('2026-11-05 12:05:00');
});

it('opens a new low battery alert on a later drop and keeps the resolved one', function () {
    $device = Device::factory()->online()->lowBattery()->create();
    $this->artisan('alerts:evaluate')->assertSuccessful();
    $device->update(['battery_level' => 100]);
    $this->artisan('alerts:evaluate')->assertSuccessful();
    $device->update(['battery_level' => 5]);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::whereNotNull('resolved_at')->count())->toBe(1)
        ->and(Alert::whereNull('resolved_at')->count())->toBe(1);
});

it('opens a low battery alert for an unassigned device', function () {
    $unassigned = Device::factory()->online()->lowBattery()->create(['farm_id' => null]);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::sole()->subject_id)->toBe($unassigned->id);
});

it('stamps an alert on a device already low on battery with the detection time', function () {
    $this->travelTo('2026-11-05 12:00:00');
    Device::factory()->online()->lowBattery()->create(['last_seen_at' => '2026-10-01 08:00:00']);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::sole()->opened_at->toDateTimeString())->toBe('2026-11-05 12:00:00');
});

it('keeps an open offline alert when the battery alert resolves', function () {
    $device = Device::factory()->offline()->lowBattery()->create();
    $this->artisan('alerts:evaluate')->assertSuccessful();
    $device->update(['battery_level' => 100]);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::open()->sole()->rule)->toBe(AlertRule::DEVICE_OFFLINE)
        ->and(Alert::whereNotNull('resolved_at')->sole()->rule)->toBe(AlertRule::LOW_BATTERY);
});

it('keeps an open battery alert when the offline alert resolves', function () {
    $device = Device::factory()->offline()->lowBattery()->create();
    $this->artisan('alerts:evaluate')->assertSuccessful();
    $device->update(['status' => DeviceStatus::ONLINE]);

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::open()->sole()->rule)->toBe(AlertRule::LOW_BATTERY)
        ->and(Alert::whereNotNull('resolved_at')->sole()->rule)->toBe(AlertRule::DEVICE_OFFLINE);
});

it('opens the low battery alert of another tenant when it runs with a signed-in user', function () {
    $device = Device::factory()->online()->lowBattery()->create();
    $this->actingAs(User::factory()->create());

    $this->artisan('alerts:evaluate')->assertSuccessful();

    expect(Alert::allTenants()->sole()->subject_id)->toBe($device->id);
});
