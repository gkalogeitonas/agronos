<?php

use App\Enums\DeviceStatus;
use App\Enums\FarmHealth;
use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use App\Models\Zone;
use App\Services\FarmOverviewService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @param  array<int, DeviceStatus>  $statuses
 */
function farmWithDevices(array $statuses): Farm
{
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    foreach ($statuses as $status) {
        Device::factory()->onFarm($farm)->create(['status' => $status]);
    }

    return $farm;
}

it('derives farm health from its devices', function (array $statuses, FarmHealth $expected) {
    $summary = app(FarmOverviewService::class)->summarize(new EloquentCollection([farmWithDevices($statuses)]))->first();

    expect($summary['health'])->toBe($expected->value);
})->with([
    'all online' => [[DeviceStatus::ONLINE, DeviceStatus::ONLINE], FarmHealth::OK],
    'some offline' => [[DeviceStatus::ONLINE, DeviceStatus::OFFLINE], FarmHealth::WARNING],
    'none online' => [[DeviceStatus::OFFLINE, DeviceStatus::ERROR], FarmHealth::OFFLINE],
    'no devices' => [[], FarmHealth::UNKNOWN],
]);

it('counts an unassigned device toward no farm', function () {
    $farm = farmWithDevices([DeviceStatus::ONLINE]);
    Device::factory()->create(['user_id' => $farm->user_id, 'status' => DeviceStatus::OFFLINE]);

    $summary = app(FarmOverviewService::class)->summarize(new EloquentCollection([$farm]))->first();

    expect($summary['devicesTotal'])->toBe(1)
        ->and($summary['health'])->toBe(FarmHealth::OK->value);
});

it('counts a device once when it has several sensors, and leaves diagnostic sensors out of the sensor count', function () {
    $farm = farmWithDevices([DeviceStatus::ONLINE]);
    $device = $farm->devices()->sole();
    Sensor::factory()->count(3)->create(['user_id' => $farm->user_id, 'device_id' => $device->id, 'type' => SensorType::MOISTURE->value]);
    Sensor::factory()->create(['user_id' => $farm->user_id, 'device_id' => $device->id, 'type' => SensorType::BATTERY->value]);

    $summary = app(FarmOverviewService::class)->summarize(new EloquentCollection([$farm]))->first();

    expect($summary['devicesTotal'])->toBe(1)
        ->and($summary['sensors'])->toBe(3);
});

it('averages the latest readings per sensor type, ignoring sensors without a reading', function () {
    $farm = farmWithDevices([DeviceStatus::ONLINE]);
    $sensorOnFarm = ['user_id' => $farm->user_id, 'device_id' => $farm->devices()->sole()->id];

    Sensor::factory()->create([...$sensorOnFarm, 'type' => SensorType::MOISTURE->value, 'last_reading' => 30]);
    Sensor::factory()->create([...$sensorOnFarm, 'type' => SensorType::MOISTURE->value, 'last_reading' => 35.5]);
    Sensor::factory()->create([...$sensorOnFarm, 'type' => SensorType::MOISTURE->value, 'last_reading' => null]);
    Sensor::factory()->create([...$sensorOnFarm, 'type' => SensorType::HUMIDITY->value, 'last_reading' => 90]);

    $summary = app(FarmOverviewService::class)->summarize(new EloquentCollection([$farm]))->first();

    expect($summary['moisture'])->toBe(32.8)
        ->and($summary['temperature'])->toBeNull();
});

it('counts each farm\'s zones, giving 0 to a farm without zones', function () {
    $zoned = Farm::factory()->create();
    $bare = Farm::factory()->create();
    Zone::factory()->count(3)->for($zoned)->create();

    $summaries = app(FarmOverviewService::class)->summarize(new EloquentCollection([$zoned, $bare]))->keyBy('id');

    expect($summaries[$zoned->id]['zones'])->toBe(3)
        ->and($summaries[$bare->id]['zones'])->toBe(0);
});
