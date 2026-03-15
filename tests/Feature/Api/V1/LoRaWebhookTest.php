<?php

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Sensor;

/**
 * Helper: build the hex-encoded LoRa raw_payload.
 *
 * Layout: [device_id (4B LE)] [fcnt (4B LE)] [AES-128-CTR encrypted plaintext]
 */
function buildLoRaRawPayloadHex(Device $device, int $fcnt, string $plaintext): string
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

    return bin2hex(pack('V', $device->id).pack('V', $fcnt).$ciphertext);
}

/**
 * Helper: build a valid EMQX webhook envelope.
 */
function buildWebhookPayload(string $rawPayloadHex, int $rssi = -85, float $snr = 7.5, string $gatewayId = 'gateway-user'): array
{
    return [
        'request' => [
            'username' => $gatewayId,
            'payload' => json_encode([
                'gateway_id' => $gatewayId,
                'rssi' => $rssi,
                'snr' => $snr,
                'raw_payload' => $rawPayloadHex,
            ]),
        ],
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
    $rawPayloadHex = buildLoRaRawPayloadHex($device, $fcnt, $plaintext);
    $webhookData = buildWebhookPayload($rawPayloadHex, -72, 9.0);

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
    $rawPayloadHex = buildLoRaRawPayloadHex($device, 100, $plaintext);
    $webhookData = buildWebhookPayload($rawPayloadHex);

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
    $rawPayloadHex = buildLoRaRawPayloadHex($device, 10_001, $plaintext);
    $webhookData = buildWebhookPayload($rawPayloadHex);

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Frame counter gap exceeded.']);
});

// ---------- Device Lookup ----------

it('returns 404 for unknown device ID', function () {
    // Pack a device ID (99999) that does not exist in the database
    $rawPayloadHex = bin2hex(pack('V', 99999).pack('V', 1).str_repeat("\x00", 6));
    $webhookData = buildWebhookPayload($rawPayloadHex);

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(404)
        ->assertJsonFragment(['message' => 'LoRa device not found.']);
});

it('rejects a non-LoRa device type', function () {
    $device = Device::factory()->create(['type' => DeviceType::WIFI->value]);

    // Send the WIFI device's numeric ID — it won't match when filtered by LORA type
    $rawPayloadHex = bin2hex(pack('V', $device->id).pack('V', 1).str_repeat("\x00", 6));
    $webhookData = buildWebhookPayload($rawPayloadHex);

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(404)
        ->assertJsonFragment(['message' => 'LoRa device not found.']);
});

// ---------- Validation ----------

it('returns 422 for missing outer payload field', function () {
    $response = $this->postJson('/api/v1/lora/webhook', [
        'request' => ['username' => 'gateway-user'],
    ]);

    $response->assertStatus(422);
});

it('returns 422 for invalid inner JSON', function () {
    $response = $this->postJson('/api/v1/lora/webhook', [
        'request' => [
            'username' => 'gateway-user',
            'payload' => 'not-valid-json',
        ],
    ]);

    $response->assertStatus(422);
});

it('returns 422 for missing inner payload fields', function () {
    $response = $this->postJson('/api/v1/lora/webhook', [
        'request' => [
            'username' => 'gateway-user',
            'payload' => json_encode(['gateway_id' => 'some-gateway']),
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors']);
});

// ---------- Decryption Failure ----------

it('returns 422 when raw_payload ciphertext length is not a multiple of 6', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 0]);

    // Ciphertext is 5 bytes ('short') — (5 % 6 ≠ 0) so validation rejects it
    $rawPayloadHex = bin2hex(pack('V', $device->id).pack('V', 1).'short');
    $webhookData = buildWebhookPayload($rawPayloadHex);

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(422);
});

// ---------- Real Device Test Vector: Test-LoRa-Battery ----------

it('processes a real Test-LoRa-Battery hardware payload and records Battery Level 100.00', function () {
    // Real-device parameters from Test-Device-LoRa-Battery.h
    //   LORA_DEVICE_ID = 3,  UUID = "Test-LoRa-Battery"
    //   LORA_AES_KEY   = 000102030405060708090a0b0c0d0e0f
    // Observed transmission (fcnt = 801):
    //   Raw payload       : 426174741027  ("Batt" + int16LE(10000) → Battery Level 100.00)
    //   Encrypted payload : D88264C76A12
    $device = Device::factory()->lora()->create([
        'id' => 3,
        'uuid' => 'Test-LoRa-Battery',
        'lora_aes_key' => '000102030405060708090a0b0c0d0e0f',
        'lora_frame_counter' => 800,
        'status' => DeviceStatus::OFFLINE,
    ]);

    $batterySensor = Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $device->user_id,
        'type' => SensorType::BATTERY->value,
        'uuid' => 'Battery-Level-1',
    ]);

    // raw_payload: [device_id=3 (4B LE)] [fcnt=801 (4B LE)] [ciphertext D88264C76A12]
    $rawPayloadHex = bin2hex(pack('V', 3).pack('V', 801).hex2bin('D88264C76A12'));
    $webhookData = buildWebhookPayload($rawPayloadHex, -90, 5.0);

    $response = $this->postJson('/api/v1/lora/webhook', $webhookData);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'LoRa data processed.']);

    $device->refresh();
    expect($device->status)->toBe(DeviceStatus::ONLINE);
    expect($device->lora_frame_counter)->toBe(801);

    $batterySensor->refresh();
    expect((float) $batterySensor->last_reading)->toBe(100.0);
    expect($batterySensor->last_reading_at)->not->toBeNull();
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
    $this->postJson('/api/v1/lora/webhook', buildWebhookPayload(buildLoRaRawPayloadHex($device, 1, $plaintext)))
        ->assertStatus(200);

    // Second packet
    $device->refresh();
    $this->postJson('/api/v1/lora/webhook', buildWebhookPayload(buildLoRaRawPayloadHex($device, 2, $plaintext)))
        ->assertStatus(200);

    $device->refresh();
    expect($device->lora_frame_counter)->toBe(2);
});
