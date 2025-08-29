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
        ->where('farmStats.totalSensors', $farm->sensors()->count())
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
        ->has('farmStats')
        ->where('farmStats.totalSensors', 4)
        ->has('farmStats.sensorTypeStats')
        ->where('farmStats.sensorTypeStats.temperature', 2)
        ->where('farmStats.sensorTypeStats.humidity', 1)
        ->where('farmStats.sensorTypeStats.moisture', 1)
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
