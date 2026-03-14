<?php

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Sensor;

/**
 * Helper: encrypt a 7-byte sensor payload for a given device/fcnt.
 */
function encryptLoRaPayload(Device $device, int $fcnt, string $plaintext): string
{
    $key = hex2bin($device->lora_aes_key);
    $nonce = pack('V', $device->id).pack('V', $fcnt).str_repeat("\x00", 8);

    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-128-ctr',
        $key,
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        $nonce,
    );

    return base64_encode($ciphertext);
}

/**
 * Helper: build a valid webhook payload.
 */
function buildWebhookPayload(Device $device, int $fcnt, string $base64Payload, int $rssi = -85, float $snr = 7.5): array
{
    return [
        'username' => 'gateway-user',
        'payload' => json_encode([
            'device_id' => $device->uuid,
            'fcnt' => $fcnt,
            'payload' => $base64Payload,
            'rssi' => $rssi,
            'snr' => $snr,
        ]),
    ];
}

/**
 * Helper: build a binary payload in the UUID-prefix format.
 *
 * Each reading is 6 bytes: [4-char UUID prefix (ASCII)][int16 LE value×100]
 * Defaults to a single temperature reading for generic tests that only need valid bytes.
 *
 * @param  array<array{string, int}>  $readings  Each element: [4-char prefix, scaled int16 value]
 */
function buildSensorBinary(array $readings = [['temp', 2550]]): string
{
    $binary = '';
    foreach ($readings as [$prefix, $scaledValue]) {
        $binary .= $prefix.pack('v', $scaledValue & 0xFFFF);
    }

    return $binary;
}

// ---------- Successful Flow ----------

it('processes a valid LoRa webhook and updates device + sensors', function () {
    $device = Device::factory()->lora()->create([
        'lora_frame_counter' => 0,
        'status' => DeviceStatus::OFFLINE,
    ]);

    // Pre-register sensors for this device
    $tempSensor = Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $device->user_id,
        'type' => SensorType::TEMPERATURE->value,
        'uuid' => 'temp-sensor-1',
    ]);
    $humSensor = Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $device->user_id,
        'type' => SensorType::HUMIDITY->value,
        'uuid' => 'hum-sensor-1',
    ]);
    $moistSensor = Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $device->user_id,
        'type' => SensorType::MOISTURE->value,
        'uuid' => 'moist-sensor-1',
    ]);
    $batSensor = Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $device->user_id,
        'type' => SensorType::BATTERY->value,
        'uuid' => 'bat-sensor-1',
    ]);

    $plaintext = buildSensorBinary([
        ['temp', 2550],   // 25.50°C — matches 'temp-sensor-1'
        ['hum-', 6500],   // 65.00%  — matches 'hum-sensor-1'
        ['mois', 4230],   // 42.30%  — matches 'moist-sensor-1'
        ['bat-', 8800],   // 88.00%  — matches 'bat-sensor-1'
    ]);
    $fcnt = 1;
    $encrypted = encryptLoRaPayload($device, $fcnt, $plaintext);
    $webhookData = buildWebhookPayload($device, $fcnt, $encrypted, -72, 9.0);

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'LoRa data processed.']);

    // Device updated
    $device->refresh();
    expect($device->status)->toBe(DeviceStatus::ONLINE);
    expect($device->lora_frame_counter)->toBe(1);
    // signal_strength and battery_level are not recorded from LoRa gateway

    // Sensors updated
    $tempSensor->refresh();
    expect((float) $tempSensor->last_reading)->toBe(25.5);
    expect($tempSensor->last_reading_at)->not->toBeNull();

    $humSensor->refresh();
    expect((float) $humSensor->last_reading)->toBe(65.0);

    $moistSensor->refresh();
    expect((float) $moistSensor->last_reading)->toBe(42.3);

    $batSensor->refresh();
    expect((float) $batSensor->last_reading)->toBe(88.0);
});

// ---------- Anti-Replay ----------

it('rejects a replayed frame counter with 409', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 100]);

    $plaintext = buildSensorBinary();
    $encrypted = encryptLoRaPayload($device, 100, $plaintext);
    $webhookData = buildWebhookPayload($device, 100, $encrypted);

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(409)
        ->assertJsonFragment(['message' => 'Replay detected.']);

    // Frame counter unchanged
    $device->refresh();
    expect($device->lora_frame_counter)->toBe(100);
});

it('rejects frame counter with excessive gap with 422', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 0]);

    $plaintext = buildSensorBinary();
    $encrypted = encryptLoRaPayload($device, 10_001, $plaintext);
    $webhookData = buildWebhookPayload($device, 10_001, $encrypted);

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Frame counter gap exceeded.']);
});

// ---------- Device Lookup ----------

it('returns 404 for unknown device UUID', function () {
    $webhookData = [
        'username' => 'gateway-user',
        'payload' => json_encode([
            'device_id' => 'nonexistent-device-uuid',
            'fcnt' => 1,
            'payload' => base64_encode('1234567'),
            'rssi' => -80,
            'snr' => 5.0,
        ]),
    ];

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(404)
        ->assertJsonFragment(['message' => 'LoRa device not found.']);
});

it('rejects a non-LoRa device type', function () {
    $device = Device::factory()->create(['type' => DeviceType::WIFI->value]);

    $webhookData = [
        'username' => 'gateway-user',
        'payload' => json_encode([
            'device_id' => $device->uuid,
            'fcnt' => 1,
            'payload' => base64_encode('1234567'),
            'rssi' => -80,
            'snr' => 5.0,
        ]),
    ];

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(404)
        ->assertJsonFragment(['message' => 'LoRa device not found.']);
});

// ---------- Validation ----------

it('returns 422 for missing outer payload field', function () {
    $response = $this->postJson('/api/v1/lora/webhook', [
        'username' => 'gateway-user',
    ]);

    $response->assertStatus(422);
});

it('returns 422 for invalid inner JSON', function () {
    $response = $this->postJson('/api/v1/lora/webhook', [
        'username' => 'gateway-user',
        'payload' => 'not-valid-json',
    ]);

    $response->assertStatus(422);
});

it('returns 422 for missing inner payload fields', function () {
    $response = $this->postJson('/api/v1/lora/webhook', [
        'username' => 'gateway-user',
        'payload' => json_encode(['device_id' => 'some-uuid']),
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors']);
});

// ---------- Decryption Failure ----------

it('returns 422 when decryption fails due to bad ciphertext', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 0]);

    $webhookData = buildWebhookPayload($device, 1, base64_encode('short'));

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    // Decryption may succeed but deserialization will fail (5 bytes is not a multiple of 6)
    $response->assertStatus(422);
});

// ---------- Frame Counter Progression ----------

it('allows sequential frame counter increments', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 0]);
    Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $device->user_id,
        'type' => SensorType::TEMPERATURE->value,
    ]);

    $plaintext = buildSensorBinary();

    // First packet
    $encrypted1 = encryptLoRaPayload($device, 1, $plaintext);
    $this->postJson('/api/v1/lora/webhook', buildWebhookPayload($device, 1, $encrypted1))
        ->assertStatus(200);

    // Second packet
    $device->refresh();
    $encrypted2 = encryptLoRaPayload($device, 2, $plaintext);
    $this->postJson('/api/v1/lora/webhook', buildWebhookPayload($device, 2, $encrypted2))
        ->assertStatus(200);

    $device->refresh();
    expect($device->lora_frame_counter)->toBe(2);
});
