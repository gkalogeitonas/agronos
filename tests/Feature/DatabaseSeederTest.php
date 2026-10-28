<?php

use App\Models\Device;
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

    expect(User::query()->where('email', 'johndoe@gmail.com')->count())->toBe(1);
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
