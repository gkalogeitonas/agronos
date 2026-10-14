<?php

use App\Enums\DeviceStatus;
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

it('derives farm status from its devices', function (array $statuses, string $expected) {
    $summary = app(FarmOverviewService::class)->summarize(new EloquentCollection([farmWithDevices($statuses)]))->first();

    expect($summary['status'])->toBe($expected);
})->with([
    'all online' => [[DeviceStatus::ONLINE, DeviceStatus::ONLINE], 'ok'],
    'some offline' => [[DeviceStatus::ONLINE, DeviceStatus::OFFLINE], 'warning'],
    'none online' => [[DeviceStatus::OFFLINE, DeviceStatus::ERROR], 'offline'],
    'no devices' => [[], 'unknown'],
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
