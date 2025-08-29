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
        ->has('farmStats')
        ->has('recentReadings')
        ->where('farmStats.totalSensors', $farm->sensors()->count())
    );
});

it('farm time series service returns expected data structure', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    $service = app(FarmTimeSeriesService::class);
    $stats = $service->farmStats($farm, '-24h');

    expect($stats)->toHaveKeys([
        'totalSensors',
        'activeSensors',
        'avgReading',
        'minReading',
        'maxReading',
        'totalReadings',
        'sensorTypeStats',
    ]);

    expect($stats['totalSensors'])->toBe($farm->sensors()->count());
    expect($stats['sensorTypeStats'])->toBeArray();
});

it('farm recent readings returns array format', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    $service = app(FarmTimeSeriesService::class);
    $readings = $service->farmRecentReadings($farm, '-24h', 10);

    expect($readings)->toBeArray();

    // If there are readings, they should have the correct structure
    if (! empty($readings)) {
        expect($readings[0])->toHaveKeys(['time', 'value']);
    }
});

it('farm service correctly calculates sensor type statistics', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);

    // Create sensors with different types (using valid enum values)
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'temperature']);
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'temperature']);
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'humidity']);
    Sensor::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'type' => 'moisture']);

    $service = app(FarmTimeSeriesService::class);
    $stats = $service->farmStats($farm, '-24h');

    expect($stats['totalSensors'])->toBe(4);
    expect($stats['sensorTypeStats'])->toHaveKey('temperature');
    expect($stats['sensorTypeStats'])->toHaveKey('humidity');
    expect($stats['sensorTypeStats'])->toHaveKey('moisture');
    expect($stats['sensorTypeStats']['temperature'])->toBe(2);
    expect($stats['sensorTypeStats']['humidity'])->toBe(1);
    expect($stats['sensorTypeStats']['moisture'])->toBe(1);
});
