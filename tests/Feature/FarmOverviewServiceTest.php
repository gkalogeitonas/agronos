<?php

use App\Enums\DeviceStatus;
use App\Enums\FarmStatus;
use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
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
        $device = Device::factory()->create(['user_id' => $user->id, 'status' => $status]);
        Sensor::factory()->create(['user_id' => $user->id, 'farm_id' => $farm->id, 'device_id' => $device->id]);
    }

    return $farm;
}

it('derives farm status from its devices', function (array $statuses, FarmStatus $expected) {
    $summary = app(FarmOverviewService::class)->summarize(new EloquentCollection([farmWithDevices($statuses)]))->first();

    expect($summary['status'])->toBe($expected->value);
})->with([
    'all online' => [[DeviceStatus::ONLINE, DeviceStatus::ONLINE], FarmStatus::OK],
    'some offline' => [[DeviceStatus::ONLINE, DeviceStatus::OFFLINE], FarmStatus::WARNING],
    'none online' => [[DeviceStatus::OFFLINE, DeviceStatus::ERROR], FarmStatus::OFFLINE],
    'no devices' => [[], FarmStatus::UNKNOWN],
]);

it('counts a device once when it has several sensors on the farm', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);
    $device = Device::factory()->create(['user_id' => $user->id, 'status' => DeviceStatus::ONLINE]);
    Sensor::factory()->count(3)->create(['user_id' => $user->id, 'farm_id' => $farm->id, 'device_id' => $device->id]);

    $summary = app(FarmOverviewService::class)->summarize(new EloquentCollection([$farm]))->first();

    expect($summary['devicesTotal'])->toBe(1)
        ->and($summary['sensors'])->toBe(3);
});

it('averages the latest readings per sensor type, ignoring sensors without a reading', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);
    $device = Device::factory()->create(['user_id' => $user->id]);
    $sensorOnFarm = ['user_id' => $user->id, 'farm_id' => $farm->id, 'device_id' => $device->id];

    Sensor::factory()->create([...$sensorOnFarm, 'type' => SensorType::MOISTURE->value, 'last_reading' => 30]);
    Sensor::factory()->create([...$sensorOnFarm, 'type' => SensorType::MOISTURE->value, 'last_reading' => 35.5]);
    Sensor::factory()->create([...$sensorOnFarm, 'type' => SensorType::MOISTURE->value, 'last_reading' => null]);
    Sensor::factory()->create([...$sensorOnFarm, 'type' => SensorType::HUMIDITY->value, 'last_reading' => 90]);

    $summary = app(FarmOverviewService::class)->summarize(new EloquentCollection([$farm]))->first();

    expect($summary['moisture'])->toBe(32.8)
        ->and($summary['temperature'])->toBeNull();
});
