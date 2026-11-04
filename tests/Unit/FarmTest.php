<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Farm model', function () {
    it('can be created with valid attributes', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id]);
        expect($farm)->toBeInstanceOf(Farm::class);
        expect($farm->user_id)->toBe($user->id);
    });

    it('belongs to a user', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id]);
        expect($farm->user)->toBeInstanceOf(User::class);
    });

    it('calculates center attribute', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create([
            'user_id' => $user->id,
            'coordinates' => [
                'coordinates' => [
                    [[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]],
                ],
            ],
        ]);
        $center = $farm->center;
        expect($center)->toHaveKeys(['lng', 'lat']);
    });

    it('returns the sensors of the devices on the farm', function () {
        $farm = Farm::factory()->create();
        $device = Device::factory()->onFarm($farm)->create();
        $sensors = Sensor::factory()->count(3)->create(['user_id' => $farm->user_id, 'device_id' => $device->id]);
        $sensorWithOnlyItsOwnFarm = Sensor::factory()->create(['farm_id' => $farm->id]);
        $farmSensors = $farm->sensors;
        expect($farmSensors)->toHaveCount(3);
        foreach ($sensors as $sensor) {
            expect($farmSensors->pluck('id'))->toContain($sensor->id);
        }
        expect($farmSensors->pluck('id'))->not->toContain($sensorWithOnlyItsOwnFarm->id);
    });
});
