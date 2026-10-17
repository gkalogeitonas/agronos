<?php

use App\Http\Requests\SensorRequest\SensorRequest;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = Device::factory(['user_id' => $this->user->id])->create();
});

it('creates a new sensor on first scan', function () {
    actingAs($this->user);
    $payload = [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'test-uuid-123',
        'lat' => 10.1234567,
        'lon' => 20.7654321,
    ];
    $response = postJson(route('sensors.scan'), $payload);
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Sensor added.');
    $this->assertDatabaseHas('sensors', ['uuid' => 'test-uuid-123', 'device_id' => $this->device->id]);
});

it('updates an existing sensor on repeated scan', function () {
    actingAs($this->user);
    $sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'uuid' => 'test-uuid-123',
        'lat' => 1.0,
        'lon' => 2.0,
        'type' => 'moisture',
        'name' => 'Old Name',
    ]);
    $payload = [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'test-uuid-123',
        'lat' => 89.9999999,
        'lon' => 88.8888888,
        'type' => 'humidity',
        'name' => 'Updated Name',
    ];
    $response = postJson(route('sensors.scan'), $payload);
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Sensor updated.');
    $this->assertDatabaseHas('sensors', [
        'uuid' => 'test-uuid-123',
        'device_id' => $this->device->id,
        'lat' => 89.9999999,
        'lon' => 88.8888888,
        'type' => 'humidity',
        'name' => 'Updated Name',
    ]);
});

it('rejects a manually entered uuid of an existing sensor until the update is confirmed', function () {
    actingAs($this->user);
    $sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'uuid' => 'test-uuid-123',
        'name' => 'Old Name',
    ]);
    $payload = [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'test-uuid-123',
        'name' => 'Typo Name',
        'manual_entry' => true,
    ];

    $response = postJson(route('sensors.scan'), $payload);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors([
        'confirm_update' => 'This UUID belongs to an existing sensor "Old Name". Confirm to update it, or check the UUID.',
    ]);
    $this->assertDatabaseHas('sensors', ['id' => $sensor->id, 'name' => 'Old Name']);
});

it('updates an existing sensor from manual entry once the update is confirmed', function () {
    actingAs($this->user);
    $sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'uuid' => 'test-uuid-123',
        'name' => 'Old Name',
    ]);
    $payload = [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'test-uuid-123',
        'name' => 'Updated Name',
        'manual_entry' => true,
        'confirm_update' => true,
    ];

    $response = postJson(route('sensors.scan'), $payload);

    $response->assertRedirect(route('sensors.index'));
    $response->assertSessionHas('success', 'Sensor updated.');
    $this->assertDatabaseHas('sensors', ['id' => $sensor->id, 'name' => 'Updated Name']);
});

it('creates a sensor from manual entry of a new uuid', function () {
    actingAs($this->user);
    $payload = [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'manual-uuid-456',
        'manual_entry' => true,
    ];

    $response = postJson(route('sensors.scan'), $payload);

    $response->assertRedirect(route('sensors.index'));
    $response->assertSessionHas('success', 'Sensor added.');
    $this->assertDatabaseHas('sensors', ['uuid' => 'manual-uuid-456', 'device_id' => $this->device->id]);
});

it('cannot update another user\'s sensor by scanning', function () {
    $otherUser = User::factory()->create();
    $otherDevice = Device::factory(['user_id' => $otherUser->id])->create();
    $sensor = Sensor::factory()->create([
        'user_id' => $otherUser->id,
        'device_id' => $otherDevice->id,
        'uuid' => 'other-uuid-123',
        'lat' => 1.0,
        'lon' => 2.0,
        'type' => 'moisture',
        'name' => 'Other User Sensor',
    ]);
    actingAs($this->user);
    $payload = [
        'device_uuid' => $otherDevice->uuid,
        'uuid' => 'other-uuid-123',
        'lat' => 89.9999999,
        'lon' => 88.8888888,
        'type' => 'humidity',
        'name' => 'Hacked Name',
    ];
    $response = postJson(route('sensors.scan'), $payload);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['device_uuid' => SensorRequest::UNREGISTERED_DEVICE_MESSAGE]);
    $this->assertDatabaseMissing('sensors', [
        'id' => $sensor->id,
        'lat' => 89.9999999,
        'lon' => 88.8888888,
        'type' => 'humidity',
        'name' => 'Hacked Name',
    ]);
});

it('sensor created by scanning is only visible to the creator', function () {
    actingAs($this->user);
    $payload = [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'unique-scan-uuid',
        'lat' => 12.345,
        'lon' => 67.890,
        'name' => 'Scan Created Sensor',
    ];
    postJson(route('sensors.scan'), $payload)->assertRedirect();
    // User who created the sensor can see it
    $response = get('/sensors');
    $response->assertOk();
    $response->assertSee('Scan Created Sensor');

    // Another user cannot see the sensor
    $otherUser = User::factory()->create();
    actingAs($otherUser);
    $response = get('/sensors');
    $response->assertOk();
    $response->assertDontSee('Scan Created Sensor');
});

it('cannot create a sensor by scanning with a device that does not belong to the user', function () {
    $otherUser = User::factory()->create();
    $otherDevice = Device::factory(['user_id' => $otherUser->id])->create();
    actingAs($this->user);
    $payload = [
        'device_uuid' => $otherDevice->uuid,
        'uuid' => 'scan-wrong-owner-device',
        'lat' => 1.23,
        'lon' => 4.56,
        'name' => 'Should Not Be Created',
    ];
    $response = postJson(route('sensors.scan'), $payload);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['device_uuid' => SensorRequest::UNREGISTERED_DEVICE_MESSAGE]);
    $this->assertDatabaseMissing('sensors', ['uuid' => 'scan-wrong-owner-device']);
});

it('tells the user to register the device first when the device uuid does not exist', function () {
    actingAs($this->user);

    $response = postJson(route('sensors.scan'), ['device_uuid' => 'missing-device', 'uuid' => 'new-sensor']);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['device_uuid' => SensorRequest::UNREGISTERED_DEVICE_MESSAGE]);
    $this->assertDatabaseMissing('sensors', ['uuid' => 'new-sensor']);
});

it('rejects a sensor uuid that belongs to another tenant without changing that sensor', function () {
    $otherSensor = Sensor::factory()->create(['uuid' => 'taken-uuid', 'name' => 'Other Sensor']);
    actingAs($this->user);

    $response = postJson(route('sensors.scan'), [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'taken-uuid',
        'name' => 'My Sensor',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['uuid' => 'This sensor UUID cannot be used. Check the UUID.']);
    $this->assertDatabaseHas('sensors', ['id' => $otherSensor->id, 'name' => 'Other Sensor', 'user_id' => $otherSensor->user_id]);
});
