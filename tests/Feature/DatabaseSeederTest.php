<?php

use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Farm;
use App\Models\User;
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
