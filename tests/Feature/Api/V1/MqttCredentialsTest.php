<?php

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('creates mqtt credentials on first request and returns created=true', function () {
    $device = Device::factory()->create([
        'uuid' => 'device-abc',
        'status' => DeviceStatus::OFFLINE,
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    fakeEmqxProvisioning('device-abc');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials');

    // dd($response->json());
    $response->assertStatus(200)->assertJson(['created' => true, 'topic_base' => 'agronos/devices/device-abc']);
    $device->refresh();
    expect($device->mqtt_username)->toBe('device-abc');
    expect($device->mqtt_password)->not->toBeNull();
    expect(emqxRulesWritten('device-abc', 'agronos/devices/device-abc'))->toBeTrue();
});

it('returns existing creds and created=false on subsequent calls', function () {
    $device = Device::factory()->create([
        'uuid' => 'device-xyz',
        'status' => DeviceStatus::OFFLINE,
        'mqtt_username' => 'device-xyz',
        'mqtt_password' => 'secret123',
    ]);

    $token = $device->createToken('device-token')->plainTextToken;
    fakeEmqxProvisioning('device-xyz', userStatus: 409);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials');

    $response->assertStatus(200)->assertJson(['created' => false, 'username' => 'device-xyz', 'topic_base' => 'agronos/devices/device-xyz']);
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/authentication/password_based:built_in_database/users/device-xyz')
        && $request['password'] === 'secret123');
    expect(emqxRulesWritten('device-xyz', 'agronos/devices/device-xyz'))->toBeTrue();
});

it('returns the topic base under the configured prefix', function () {
    config(['services.emqx.topic_prefix' => 'farm-a']);
    $device = Device::factory()->create([
        'uuid' => 'device-xyz',
        'mqtt_username' => 'device-xyz',
        'mqtt_password' => 'secret123',
    ]);

    $token = $device->createToken('device-token')->plainTextToken;
    fakeEmqxProvisioning('device-xyz', userStatus: 409);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials')
        ->assertOk()
        ->assertJson(['topic_base' => 'farm-a/devices/device-xyz']);
    expect(emqxRulesWritten('device-xyz', 'farm-a/devices/device-xyz'))->toBeTrue();
});

it('does not create credentials when broker is offline and returns no error', function () {
    $device = Device::factory()->create([
        'uuid' => 'device-offline',
        'status' => DeviceStatus::OFFLINE,
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    fakeEmqxProvisioning('device-offline', userStatus: 500);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials');

    $response->assertStatus(503)->assertJson(['created' => false]);

    $device->refresh();
    expect($device->mqtt_username)->toBeNull();
    expect($device->mqtt_password)->toBeNull();
});

it('returns 503 and does not save new credentials when the broker rejects the rules', function () {
    $device = Device::factory()->create(['uuid' => 'device-norules']);
    $token = $device->createToken('device-token')->plainTextToken;
    fakeEmqxProvisioning('device-norules', rulesStatus: 500);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials')
        ->assertServiceUnavailable()
        ->assertJson(['created' => false, 'error' => 'emqx_unavailable']);

    expect($device->refresh()->mqtt_password)->toBeNull();
});
