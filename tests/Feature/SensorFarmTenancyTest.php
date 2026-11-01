<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = Device::factory(['user_id' => $this->user->id])->create();
    $this->otherFarm = Farm::factory()->create(['user_id' => User::factory()->create()->id]);
    actingAs($this->user);
});

it('rejects creating a sensor on another tenant farm', function () {
    postJson(route('sensors.store'), [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'sensor-cross-farm',
        'farm_id' => $this->otherFarm->id,
    ])->assertJsonValidationErrors('farm_id');

    $this->assertDatabaseMissing('sensors', ['farm_id' => $this->otherFarm->id]);
});

it('rejects moving a sensor to another tenant farm', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);

    putJson(route('sensors.update', $sensor), ['farm_id' => $this->otherFarm->id])
        ->assertJsonValidationErrors('farm_id');

    $this->assertDatabaseMissing('sensors', ['farm_id' => $this->otherFarm->id]);
});

it('rejects scanning a sensor onto another tenant farm', function () {
    postJson(route('sensors.scan'), [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'sensor-scan-cross-farm',
        'farm_id' => $this->otherFarm->id,
    ])->assertJsonValidationErrors('farm_id');

    $this->assertDatabaseMissing('sensors', ['farm_id' => $this->otherFarm->id]);
});

it('accepts the user own farm', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);

    postJson(route('sensors.store'), [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'sensor-own-farm',
        'farm_id' => $farm->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('sensors', ['uuid' => 'sensor-own-farm', 'farm_id' => $farm->id]);
});
