<?php

use App\Models\Farm;
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

    it('returns sensors related to the farm', function () {
        $farm = \App\Models\Farm::factory()->create();
        $sensors = \App\Models\Sensor::factory()->count(3)->create(['farm_id' => $farm->id]);
        $farmSensors = $farm->sensors;
        expect($farmSensors)->toHaveCount(3);
        foreach ($sensors as $sensor) {
            expect($farmSensors->pluck('id'))->toContain($sensor->id);
        }
    });
});
