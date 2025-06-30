<?php

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a device with a hashed secret
    $this->device = Device::factory()->create([
        'uuid' => 'test-device-uuid-123',
        'secret' => Hash::make('super-secret-key'),
    ]);
});

test('device can login with valid uuid and secret', function () {
    $response = $this->postJson('/api/device/login', [
        'uuid' => 'test-device-uuid-123',
        'secret' => 'super-secret-key',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token']);
});

test('device cannot login with invalid uuid', function () {
    $response = $this->postJson('/api/device/login', [
        'uuid' => 'invalid-uuid',
        'secret' => 'super-secret-key',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid credentials']);
});

test('device cannot login with invalid secret', function () {
    $response = $this->postJson('/api/device/login', [
        'uuid' => 'test-device-uuid-123',
        'secret' => 'wrong-secret',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid credentials']);
});

test('device login requires uuid and secret', function () {
    $response = $this->postJson('/api/device/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['uuid', 'secret']);
});
