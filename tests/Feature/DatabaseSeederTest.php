<?php

use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Planting;
use App\Models\Sensor;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevEnvironmentSeeder;
use Illuminate\Support\Facades\Hash;

test('the database seeder creates a verified demo user', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'johndoe@gmail.com')->sole();

    expect($user->name)->toBe('john Doe')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('the database seeder can run more than once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'johndoe@gmail.com')->count())->toBe(1)
        ->and(Farm::allTenants()->where('name', 'Τριποδες')->count())->toBe(1);
});

test('seeded devices can log in to the device API with the dev secret', function () {
    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/v1/device/login', [
        'uuid' => 'Test-Device-1',
        'secret' => DevEnvironmentSeeder::DEVICE_SECRET,
    ])->assertOk()->assertJsonStructure(['token']);
});

test('seeding resets the dev secret of an existing device', function () {
    Device::factory()->create(['uuid' => 'Test-Device-1', 'secret' => Hash::make('old-secret')]);

    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/v1/device/login', [
        'uuid' => 'Test-Device-1',
        'secret' => DevEnvironmentSeeder::DEVICE_SECRET,
    ])->assertOk();
});

test('the dev environment seeder does not create devices with the public secret in production', function () {
    $this->app['env'] = 'production';

    $this->seed(DatabaseSeeder::class);

    expect(Device::allTenants()->where('uuid', 'Test-Device-1')->exists())->toBeFalse();
});

test('the seeded device with sensors is on a farm', function () {
    $this->seed(DatabaseSeeder::class);

    $device = Device::allTenants()->with('sensors')->where('uuid', 'Test-Device-1')->sole();

    expect($device->farm_id)->not->toBeNull()
        ->and($device->sensors)->not->toBeEmpty();
});

test('the dev environment seeder adds a large farm of 50 devices, each with one or two sensors and a battery sensor', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $farm = Farm::allTenants()->where('name', DevEnvironmentSeeder::LARGE_FARM_NAME)->sole();
    $devices = Device::allTenants()->with('sensors')->where('farm_id', $farm->id)->get();

    expect($devices)->toHaveCount(50)
        ->and($devices->every(fn (Device $device) => $device->user_id === $farm->user_id))->toBeTrue();

    $devices->each(function (Device $device) {
        $battery = $device->sensors->where('type', SensorType::BATTERY->value);
        $readingSensors = $device->sensors->where('type', '!=', SensorType::BATTERY->value);

        expect($battery)->toHaveCount(1)
            ->and($readingSensors->count())->toBeBetween(1, 2);
    });
});

test('the dev environment seeder puts the reading sensors of Τριποδες in a zone and leaves its battery sensor out', function () {
    $this->seed(DatabaseSeeder::class);

    $farm = Farm::allTenants()->where('name', 'Τριποδες')->sole();
    $zones = Zone::allTenants()->withCount('sensors')->where('farm_id', $farm->id)->get();
    $sensors = Sensor::allTenants()->with('zone')->whereIn('uuid', ['Test-Device-1-sensor-1', 'Test-Device-1-sensor-2', 'Test-Device-1-battery'])->get()->keyBy('uuid');

    expect($zones->count())->toBeGreaterThanOrEqual(2)
        ->and($zones->every(fn (Zone $zone) => $zone->user_id === $farm->user_id))->toBeTrue()
        ->and($sensors['Test-Device-1-sensor-1']->zone?->farm_id)->toBe($farm->id)
        ->and($sensors['Test-Device-1-sensor-2']->zone?->farm_id)->toBe($farm->id)
        ->and($sensors['Test-Device-1-battery']->zone_id)->toBeNull();
});

test('the dev environment seeder spreads some reading sensors of the large farm across its zones and leaves one zone empty', function () {
    $this->seed(DatabaseSeeder::class);

    $farm = Farm::allTenants()->where('name', DevEnvironmentSeeder::LARGE_FARM_NAME)->sole();
    $zones = Zone::allTenants()->withCount('sensors')->where('farm_id', $farm->id)->get();
    $sensors = Sensor::allTenants()->whereHas('device', fn ($query) => $query->where('farm_id', $farm->id))->get();
    $readingSensors = $sensors->where('type', '!=', SensorType::BATTERY->value);

    expect($zones->count())->toBeGreaterThanOrEqual(3)
        ->and($zones->where('sensors_count', 0))->not->toBeEmpty()
        ->and($zones->where('sensors_count', '>', 0)->count())->toBeGreaterThanOrEqual(2)
        ->and($readingSensors->whereNotNull('zone_id'))->not->toBeEmpty()
        ->and($readingSensors->whereNull('zone_id'))->not->toBeEmpty()
        ->and($sensors->where('type', SensorType::BATTERY->value)->whereNotNull('zone_id'))->toBeEmpty();
});

test('every seeded sensor in a zone is in a zone on its device\'s farm', function () {
    $this->seed(DatabaseSeeder::class);

    $zonedSensors = Sensor::allTenants()->with(['device', 'zone'])->whereNotNull('zone_id')->get();

    expect($zonedSensors)->not->toBeEmpty()
        ->and($zonedSensors->every(fn (Sensor $sensor) => $sensor->zone->farm_id === $sensor->device->farm_id))->toBeTrue();
});

test('re-seeding updates the seeded zones in place', function () {
    $this->seed(DatabaseSeeder::class);
    $zoneIds = Zone::allTenants()->orderBy('id')->pluck('id');
    $sensorZones = Sensor::allTenants()->orderBy('uuid')->pluck('zone_id', 'uuid');

    $this->seed(DatabaseSeeder::class);

    expect(Zone::allTenants()->orderBy('id')->pluck('id'))->toEqual($zoneIds)
        ->and(Sensor::allTenants()->orderBy('uuid')->pluck('zone_id', 'uuid'))->toEqual($sensorZones);
});

test('the dev seeder gives one farm a single whole-farm planting, once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $farm = Farm::allTenants()->where('name', 'Τριποδες')->sole();
    $plantings = Planting::allTenants()->whereBelongsTo($farm)->with('crop')->get();

    expect($plantings)->toHaveCount(1)
        ->and($plantings->first()->crop->name)->toBe('Wine grape')
        ->and($plantings->first()->zone_id)->toBeNull();
});

test('the dev seeder gives the large farm\'s zones different crops, with a past planting, once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $farm = Farm::allTenants()->where('name', DevEnvironmentSeeder::LARGE_FARM_NAME)->sole();
    $plantings = Planting::allTenants()->whereBelongsTo($farm)->with(['crop', 'zone'])->get();
    $today = now()->startOfDay();
    $currentByLand = $plantings->filter(fn (Planting $planting) => $planting->statusOn($today) === Planting::STATUS_CURRENT)
        ->mapWithKeys(fn (Planting $planting) => [$planting->zone?->name ?? 'farm' => $planting->crop->name]);

    expect($plantings)->toHaveCount(4)
        ->and($currentByLand->all())->toEqual(['farm' => 'Olive', 'Σειρά 2' => 'Tomato', 'Σειρά 3' => 'Fallow'])
        ->and($plantings->filter(fn (Planting $planting) => $planting->statusOn($today) === Planting::STATUS_PAST)->map(fn (Planting $planting) => [$planting->zone->name, $planting->crop->name])->values()->all())
        ->toEqual([['Σειρά 2', 'Wheat']]);
});
