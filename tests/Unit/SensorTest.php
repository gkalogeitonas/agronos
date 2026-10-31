<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;

it('can create a sensor', function () {
    $sensor = Sensor::factory()->create();
    expect($sensor)->toBeInstanceOf(Sensor::class);
    expect($sensor->uuid)->not->toBeEmpty();
    expect($sensor->device_id)->not->toBeNull();
    expect($sensor->farm_id)->not->toBeNull();
    expect($sensor->user_id)->not->toBeNull();
    expect($sensor->type)->not->toBeEmpty();
    expect($sensor->lat)->not->toBeNull();
    expect($sensor->lon)->not->toBeNull();
});

it('sensor belongs to a device', function () {
    $sensor = Sensor::factory()->create();
    expect($sensor->device)->toBeInstanceOf(Device::class);
});

it('sensor belongs to a farm', function () {
    $sensor = Sensor::factory()->create();
    expect($sensor->farm)->toBeInstanceOf(Farm::class);
});

it('sensor belongs to a user', function () {
    $sensor = Sensor::factory()->create();
    expect($sensor->user)->toBeInstanceOf(User::class);
});
