<?php

use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = Device::factory(['user_id' => $this->user->id])->create();
    actingAs($this->user);
});

it('can list sensors', function () {
    Sensor::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'name' => 'Test Sensor',
    ]);
    $response = get('/sensors');
    $response->assertOk();
    $response->assertSee('Test Sensor');
});

it('can show a sensor', function () {
    $sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'name' => 'Show Sensor',
    ]);
    $response = get('/sensors/'.$sensor->id);
    $response->assertOk();
    $response->assertSee('Show Sensor');
});

it('can create a sensor', function () {
    $payload = [
        'uuid' => 'test-create-uuid',
        'device_uuid' => $this->device->uuid,
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    $response->assertRedirect();
    $this->assertDatabaseHas('sensors', ['uuid' => 'test-create-uuid']);
});

it('can update a sensor', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);
    $payload = [
        'lat' => 89.99,
        'lon' => 88.88,
    ];
    $response = put('/sensors/'.$sensor->id, $payload);
    $response->assertRedirect();
    $this->assertDatabaseHas('sensors', ['id' => $sensor->id, 'lat' => 89.99, 'lon' => 88.88]);
});

it('can delete a sensor', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);
    $response = delete('/sensors/'.$sensor->id);
    $response->assertRedirect();
    $this->assertDatabaseMissing('sensors', ['id' => $sensor->id]);
});

it('cannot list sensors belonging to another user', function () {
    $otherUser = User::factory()->create();
    Sensor::factory()->count(2)->create([
        'user_id' => $otherUser->id,
        'device_id' => $this->device->id,
        'name' => 'Other User Sensor',
    ]);
    $response = get('/sensors');
    $response->assertOk();
    $response->assertDontSee('Other User Sensor');
});

it('cannot show a sensor belonging to another user', function () {
    $otherUser = User::factory()->create();
    $sensor = Sensor::factory()->create([
        'user_id' => $otherUser->id,
        'device_id' => $this->device->id,
        'name' => 'Other User Sensor',
    ]);
    $response = get('/sensors/'.$sensor->id);
    $response->assertNotFound();
});

it('cannot update a sensor belonging to another user', function () {
    $otherUser = User::factory()->create();
    $sensor = Sensor::factory()->create([
        'user_id' => $otherUser->id,
        'device_id' => $this->device->id,
        'name' => 'Other User Sensor',
    ]);
    $payload = [
        'lat' => 55.55,
        'lon' => 44.44,
    ];
    $response = put('/sensors/'.$sensor->id, $payload);
    $response->assertNotFound();
    $this->assertDatabaseMissing('sensors', ['id' => $sensor->id, 'lat' => 55.55, 'lon' => 44.44]);
});

it('cannot create a sensor without device_uuid', function () {
    $payload = [
        'uuid' => 'no-device-id',
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    $response->assertSessionHasErrors('device_uuid');
    $this->assertDatabaseMissing('sensors', ['uuid' => 'no-device-id']);
});

it('cannot create a sensor with non-existing device_uuid', function () {
    $payload = [
        'uuid' => 'bad-device-id',
        'device_uuid' => 'not-a-real-uuid',
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    $response->assertSessionHasErrors('device_uuid');
    $this->assertDatabaseMissing('sensors', ['uuid' => 'bad-device-id']);
});

it('cannot create a sensor with a device_uuid that does not belong to the user', function () {
    $otherUser = User::factory()->create();
    $otherDevice = Device::factory(['user_id' => $otherUser->id])->create();
    $payload = [
        'uuid' => 'wrong-owner-device',
        'device_uuid' => $otherDevice->uuid,
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    // You may want to add a custom validation rule for this in your controller
    $this->assertTrue(
        in_array($response->status(), [403, 404]),
        'Expected 403 Forbidden or 404 Not Found, got '.$response->status()
    );
    $this->assertDatabaseMissing('sensors', ['uuid' => 'wrong-owner-device']);
});

it('can create a sensor with a farm', function () {
    $farm = \App\Models\Farm::factory(['user_id' => $this->user->id])->create();
    $payload = [
        'uuid' => 'sensor-with-farm',
        'device_uuid' => $this->device->uuid,
        'user_id' => $this->user->id,
        'lat' => 12.34,
        'lon' => 56.78,
        'farm_id' => $farm->id,
    ];
    $response = post('/sensors', $payload);
    $response->assertRedirect();
    $this->assertDatabaseHas('sensors', [
        'uuid' => 'sensor-with-farm',
        'farm_id' => $farm->id,
    ]);
});
