<?php

use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use App\Services\TimeSeries\FarmTimeSeriesService;

it('provides farm statistics data on show page', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->get(route('farms.show', $farm));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Farms/Show')
        ->has('sensorDbStats')
        ->where('sensorDbStats.totalSensors', $farm->sensors()->count())
    );
});

it('farm time series service returns expected data structure', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    $service = app(FarmTimeSeriesService::class);
    $stats = $service->farmStats($farm, '-24h');

    expect($stats)->toHaveKeys([
        'readingStatsByType',
    ]);

    expect($stats['readingStatsByType'])->toBeArray();
});

it('farm service correctly calculates sensor type statistics', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    // Create sensors with different types (using valid enum values)
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'temperature']);
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'temperature']);
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'humidity']);
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'moisture']);

    // Test controller response which now includes database statistics
    $response = $this->actingAs($user)
        ->get(route('farms.show', $farm));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Farms/Show')
        ->has('sensorDbStats')
        ->where('sensorDbStats.totalSensors', 4)
        ->has('sensorDbStats.sensorTypeStats')
        ->where('sensorDbStats.sensorTypeStats.temperature', 2)
        ->where('sensorDbStats.sensorTypeStats.humidity', 1)
        ->where('sensorDbStats.sensorTypeStats.moisture', 1)
    );
});

it('provides last reading average per sensor type from sensors table', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    // Create devices that are online and attach sensors to them
    $device1 = \App\Models\Device::factory()->create(['user_id' => $user->id, 'status' => 'online']);
    $device2 = \App\Models\Device::factory()->create(['user_id' => $user->id, 'status' => 'online']);
    $device3 = \App\Models\Device::factory()->create(['user_id' => $user->id, 'status' => 'online']);

    // Create temperature sensors with explicit last_reading values attached to online devices
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'device_id' => $device1->id, 'type' => 'temperature', 'last_reading' => 10.0]);
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'device_id' => $device2->id, 'type' => 'temperature', 'last_reading' => 14.0]);
    // Create a humidity sensor attached to an online device
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'device_id' => $device3->id, 'type' => 'humidity', 'last_reading' => 55.5]);

    $response = $this->actingAs($user)
        ->get(route('farms.show', $farm));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Farms/Show')
        ->has('sensorDbStats')
        ->has('sensorDbStats.lastAvgByType')
        ->where('sensorDbStats.lastAvgByType.temperature', 12)
        ->where('sensorDbStats.lastAvgByType.humidity', 55.5)
    );
});

it('farm service provides reading statistics per sensor type', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    // Create sensors with different types
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'temperature']);
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'humidity']);

    $service = app(FarmTimeSeriesService::class);
    $stats = $service->farmStats($farm, '-24h');

    expect($stats['readingStatsByType'])->toBeArray();

    // Each type should have its own statistics
    foreach ($stats['readingStatsByType'] as $type => $typeStats) {
        expect($typeStats)->toHaveKeys([
            'avgReading',
            'minReading',
            'maxReading',
        ]);
    }
});

it('farmRelevant scope excludes battery sensors', function () {
    $farm = \App\Models\Farm::factory()->create();

    $battery = \App\Models\Sensor::factory()->create([
        'farm_id' => $farm->id,
        'type' => \App\Enums\SensorType::BATTERY->value,
    ]);

    $moisture = \App\Models\Sensor::factory()->count(2)->create([
        'farm_id' => $farm->id,
        'type' => \App\Enums\SensorType::MOISTURE->value,
    ]);

    expect($farm->sensors()->count())->toBe(3);

    $relevant = $farm->sensors()->farmRelevant()->get();
    expect($relevant)->toHaveCount(2);
    expect($relevant->pluck('id'))->not->toContain($battery->id);
});
