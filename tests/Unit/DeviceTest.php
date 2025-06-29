<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Device model', function () {
    it('can be created with valid attributes', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        expect($device)->toBeInstanceOf(Device::class);
        expect($device->user_id)->toBe($user->id);
    });

    it('belongs to a user', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        expect($device->user)->toBeInstanceOf(User::class);
    });
});
