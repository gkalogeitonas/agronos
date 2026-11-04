<?php

use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use App\Services\InfluxDBService;
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

    $device = Device::factory()->onFarm($farm)->create();

    // Create sensors with different types (using valid enum values)
    Sensor::factory()->create(['device_id' => $device->id, 'user_id' => $user->id, 'type' => 'temperature']);
    Sensor::factory()->create(['device_id' => $device->id, 'user_id' => $user->id, 'type' => 'temperature']);
    Sensor::factory()->create(['device_id' => $device->id, 'user_id' => $user->id, 'type' => 'humidity']);
    Sensor::factory()->create(['device_id' => $device->id, 'user_id' => $user->id, 'type' => 'moisture']);

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

    // Create devices on the farm that are online and attach sensors to them
    $device1 = Device::factory()->onFarm($farm)->create(['status' => 'online']);
    $device2 = Device::factory()->onFarm($farm)->create(['status' => 'online']);
    $device3 = Device::factory()->onFarm($farm)->create(['status' => 'online']);

    // Create temperature sensors with explicit last_reading values attached to online devices
    Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device1->id, 'type' => 'temperature', 'last_reading' => 10.0]);
    Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device2->id, 'type' => 'temperature', 'last_reading' => 14.0]);
    // Create a humidity sensor attached to an online device
    Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device3->id, 'type' => 'humidity', 'last_reading' => 55.5]);

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

    $device = Device::factory()->onFarm($farm)->create();

    // Create sensors with different types
    Sensor::factory()->create(['device_id' => $device->id, 'user_id' => $user->id, 'type' => 'temperature']);
    Sensor::factory()->create(['device_id' => $device->id, 'user_id' => $user->id, 'type' => 'humidity']);

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
    $farm = Farm::factory()->create();
    $device = Device::factory()->onFarm($farm)->create();

    $battery = Sensor::factory()->create([
        'user_id' => $farm->user_id,
        'device_id' => $device->id,
        'type' => SensorType::BATTERY->value,
    ]);

    $moisture = Sensor::factory()->count(2)->create([
        'user_id' => $farm->user_id,
        'device_id' => $device->id,
        'type' => SensorType::MOISTURE->value,
    ]);

    expect($farm->sensors()->count())->toBe(3);

    $relevant = $farm->sensors()->farmRelevant()->get();
    expect($relevant)->toHaveCount(2);
    expect($relevant->pluck('id'))->not->toContain($battery->id);
});

it('lists every device on the farm, including devices without sensors', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);
    $withSensors = Device::factory()->onFarm($farm)->create(['name' => 'A node']);
    $withoutSensors = Device::factory()->onFarm($farm)->create(['name' => 'B node']);
    Sensor::factory()->count(2)->create(['user_id' => $user->id, 'device_id' => $withSensors->id, 'type' => 'moisture']);
    Device::factory()->create(['user_id' => $user->id]);
    Device::factory()->onFarm()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('farms.show', $farm))
        ->assertInertia(fn ($page) => $page->component('Farms/Show')
            ->has('devices', 2)
            ->where('devices.0.id', $withSensors->id)
            ->where('devices.0.uuid', $withSensors->uuid)
            ->where('devices.1.id', $withoutSensors->id)
            ->missing('devices.0.secret')
            ->missing('devices.0.mqtt_password')
        );
});

it('shows the sensors of the devices on the farm, without diagnostic sensors', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);
    $device = Device::factory()->onFarm($farm)->create();
    $moisture = Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device->id, 'type' => SensorType::MOISTURE->value]);
    Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device->id, 'type' => SensorType::BATTERY->value]);
    $unassignedDevice = Device::factory()->create(['user_id' => $user->id]);
    Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $unassignedDevice->id, 'farm_id' => $farm->id, 'type' => SensorType::MOISTURE->value]);

    $this->actingAs($user)
        ->get(route('farms.show', $farm))
        ->assertInertia(fn ($page) => $page->component('Farms/Show')
            ->has('sensors', 1)
            ->where('sensors.0.id', $moisture->id)
            ->where('sensorDbStats.totalSensors', 1)
            ->where('sensorDbStats.sensorTypeStats', ['moisture' => 1])
        );
});

it('shows the first ten sensors and devices of a large farm, with totals and every sensor on the map', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);
    $devices = Device::factory()->count(12)->onFarm($farm)->sequence(fn ($sequence) => [
        'name' => sprintf('Node %02d', $sequence->index),
        'status' => $sequence->index < 3 ? 'online' : 'offline',
    ])->create();
    $devices->each(fn (Device $device) => Sensor::factory()->create([
        'user_id' => $user->id,
        'device_id' => $device->id,
        'name' => "Sensor of {$device->name}",
        'type' => SensorType::MOISTURE->value,
    ]));

    $this->actingAs($user)
        ->get(route('farms.show', $farm))
        ->assertInertia(fn ($page) => $page->component('Farms/Show')
            ->has('devices', 10)
            ->where('devices.0.name', 'Node 00')
            ->where('devicesTotal', 12)
            ->where('devicesOnline', 3)
            ->has('sensors', 10)
            ->where('sensors.0.name', 'Sensor of Node 00')
            ->where('sensorDbStats.totalSensors', 12)
            ->has('sensorLocations', 12)
            ->has('sensorLocations.0', fn ($location) => $location->hasAll(['id', 'name', 'lat', 'lon'])->etc())
        );
});

it('filters the farm time-series stats on the farm tag and the farm-relevant sensor types, not on sensor ids', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);
    $device = Device::factory()->onFarm($farm)->create();
    $sensor = Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device->id, 'type' => SensorType::MOISTURE->value]);

    $queries = [];
    $influx = Mockery::mock(InfluxDBService::class);
    $influx->shouldReceive('queryPipeline')->andReturnUsing(function (string $pipeline) use (&$queries) {
        $queries[] = $pipeline;

        return [];
    });
    $this->app->instance(InfluxDBService::class, $influx);

    $this->actingAs($user)
        ->get(route('farms.show', $farm))
        ->assertInertia(fn ($page) => $page->component('Farms/Show')
            ->missing('timeSeriesStats')
            ->loadDeferredProps(fn ($reload) => $reload->has('timeSeriesStats.readingStatsByType'))
        );

    expect($queries)->not->toBeEmpty();
    foreach ($queries as $query) {
        expect($query)
            ->toContain("r.farm_id == \"{$farm->id}\"")
            ->toContain('contains(value: r.sensor_type, set: ["moisture", "temperature", "humidity", "light", "other"])')
            ->not->toContain('sensor_id')
            ->not->toContain('"battery"');
    }
});
