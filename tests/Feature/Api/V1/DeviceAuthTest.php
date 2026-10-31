<?php

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a device with a hashed secret
    $this->device = Device::factory()->create([
        'uuid' => 'test-device-uuid-123',
        'secret' => Hash::make('super-secret-key'),
    ]);
});

test('device can login with valid uuid and secret', function () {
    $response = $this->postJson('/api/v1/device/login', [
        'uuid' => 'test-device-uuid-123',
        'secret' => 'super-secret-key',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token']);
});

test('device cannot login with invalid uuid', function () {
    $response = $this->postJson('/api/v1/device/login', [
        'uuid' => 'invalid-uuid',
        'secret' => 'super-secret-key',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid credentials']);
});

test('device cannot login with invalid secret', function () {
    $response = $this->postJson('/api/v1/device/login', [
        'uuid' => 'test-device-uuid-123',
        'secret' => 'wrong-secret',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid credentials']);
});

test('device login requires uuid and secret', function () {
    $response = $this->postJson('/api/v1/device/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['uuid', 'secret']);
});

it('updates device status to ONLINE on successful authentication', function () {
    $device = Device::factory()->create([
        'uuid' => 'test-uuid',
        'secret' => Hash::make('test-secret'),
        'status' => DeviceStatus::REGISTERED,
    ]);

    $response = $this->postJson('/api/v1/device/login', [
        'uuid' => 'test-uuid',
        'secret' => 'test-secret',
    ]);

    $response->assertStatus(200);

    $device->refresh();
    expect($device->status)->toBe(DeviceStatus::ONLINE);
});

it('revokes the previous device token when the device logs in again', function () {
    $credentials = ['uuid' => 'test-device-uuid-123', 'secret' => 'super-secret-key'];

    $firstToken = $this->postJson('/api/v1/device/login', $credentials)->json('token');
    $secondToken = $this->postJson('/api/v1/device/login', $credentials)->json('token');

    expect($this->device->tokens()->count())->toBe(1)
        ->and(PersonalAccessToken::findToken($firstToken))->toBeNull()
        ->and(PersonalAccessToken::findToken($secondToken)->tokenable->is($this->device))->toBeTrue();
});

it('does not revoke tokens of other devices on login', function () {
    $otherDevice = Device::factory()->create();
    $otherDevice->createToken('device-token');

    $this->postJson('/api/v1/device/login', [
        'uuid' => 'test-device-uuid-123',
        'secret' => 'super-secret-key',
    ])->assertStatus(200);

    expect($otherDevice->tokens()->count())->toBe(1);
});
