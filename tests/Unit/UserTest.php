<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User model', function () {
    it('can be created with valid attributes', function () {
        $user = User::factory()->create();
        expect($user)->toBeInstanceOf(User::class);
    });

    it('can have farms', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id]);
        expect($user->farms)->toHaveCount(1);
        expect($user->farms->first())->toBeInstanceOf(Farm::class);
    });

    it('can have devices', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        expect($user->devices)->toHaveCount(1);
        expect($user->devices->first())->toBeInstanceOf(Device::class);
    });

    it('a user can have many sensors', function () {
        $user = User::factory()->create();
        $sensors = Sensor::factory()->count(3)->create(['user_id' => $user->id]);
        expect($user->sensors)->toHaveCount(3);
        expect($user->sensors->first())->toBeInstanceOf(Sensor::class);
    });
});
