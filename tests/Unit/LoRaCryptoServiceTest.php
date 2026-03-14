<?php

use App\Exceptions\LoRaFrameCounterGapException;
use App\Exceptions\LoRaReplayException;
use App\Models\Device;
use App\Services\LoRaCryptoService;

beforeEach(function () {
    $this->service = new LoRaCryptoService;
});

// ---------- Frame Counter Validation ----------

it('accepts a valid frame counter and updates the device', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 100]);

    $this->service->validateFrameCounter($device, 101);

    $device->refresh();
    expect($device->lora_frame_counter)->toBe(101);
});

it('accepts a frame counter with a large but valid gap', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 0]);

    $this->service->validateFrameCounter($device, 9999);

    $device->refresh();
    expect($device->lora_frame_counter)->toBe(9999);
});

it('rejects a replayed frame counter (equal)', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 500]);

    expect(fn () => $this->service->validateFrameCounter($device, 500))
        ->toThrow(LoRaReplayException::class);
});

it('rejects a replayed frame counter (lower)', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 500]);

    expect(fn () => $this->service->validateFrameCounter($device, 499))
        ->toThrow(LoRaReplayException::class);
});

it('rejects a frame counter that exceeds MAX_FCNT_GAP', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 0]);

    expect(fn () => $this->service->validateFrameCounter($device, 10_001))
        ->toThrow(LoRaFrameCounterGapException::class);
});

it('accepts frame counter exactly at MAX_FCNT_GAP boundary', function () {
    $device = Device::factory()->lora()->create(['lora_frame_counter' => 0]);

    $this->service->validateFrameCounter($device, 10_000);

    $device->refresh();
    expect($device->lora_frame_counter)->toBe(10_000);
});

// ---------- Deserialization ----------

it('deserializes a multi-reading binary payload correctly', function () {
    // 6 bytes per reading: [4-char UUID prefix][int16 LE value×100]
    $binary = 'temp'.pack('v', 2550)   // 25.50°C
            .'humi'.pack('v', 6500)   // 65.00%
            .'mois'.pack('v', 4230)   // 42.30%
            .'batt'.pack('v', 8800);  // 88.00%

    $result = $this->service->deserialize($binary);

    expect($result)->toBe([
        'temp' => 25.50,
        'humi' => 65.00,
        'mois' => 42.30,
        'batt' => 88.00,
    ]);
});

it('handles negative values correctly', function () {
    // temp=-5.25°C: -525 as signed int16 → unsigned = 65536 - 525 = 65011
    $tempSigned = -525;
    $tempUnsigned = $tempSigned & 0xFFFF;
    $binary = 'temp'.pack('v', $tempUnsigned)
            .'humi'.pack('v', 5000)
            .'mois'.pack('v', 3000);

    $result = $this->service->deserialize($binary);

    expect($result['temp'])->toBe(-5.25);
    expect($result['humi'])->toBe(50.00);
    expect($result['mois'])->toBe(30.00);
});

it('handles zero values', function () {
    $binary = 'temp'.pack('v', 0)
            .'humi'.pack('v', 0);

    $result = $this->service->deserialize($binary);

    expect($result)->toBe(['temp' => 0.0, 'humi' => 0.0]);
});

it('throws on binary payload length not a multiple of 6', function () {
    expect(fn () => $this->service->deserialize('short'))  // 5 bytes
        ->toThrow(\InvalidArgumentException::class);
});

// ---------- Encryption / Decryption ----------

it('decrypts a known ciphertext with correct key and nonce', function () {
    // Known sensor data in UUID-prefix format (2 readings × 6 bytes each)
    $plaintext = 'temp'.pack('v', 2550)   // 25.50°C
               .'batt'.pack('v', 8800);  // 88.00%

    // Generate a deterministic key
    $hexKey = str_repeat('ab', 16); // 32-char hex → 16-byte key
    $key = hex2bin($hexKey);

    $deviceId = 42;
    $fcnt = 100;

    // Build expected nonce
    $nonce = pack('V', $deviceId).pack('V', $fcnt).str_repeat("\x00", 8);

    // Encrypt with AES-128-CTR to produce the ciphertext
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-128-ctr',
        $key,
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        $nonce
    );

    $base64 = base64_encode($ciphertext);

    // Create a device matching the expected nonce inputs
    $device = Device::factory()->lora()->create([
        'id' => $deviceId,
        'lora_aes_key' => $hexKey,
        'lora_frame_counter' => 99,
    ]);

    $decrypted = $this->service->decrypt($device, $fcnt, $base64);

    expect($decrypted)->toBe($plaintext);

    // Verify full round-trip through deserialization
    $result = $this->service->deserialize($decrypted);
    expect($result['temp'])->toBe(25.50);
    expect($result['batt'])->toBe(88.00);
});

it('throws on invalid base64 ciphertext', function () {
    $device = Device::factory()->lora()->create();

    expect(fn () => $this->service->decrypt($device, 1, '!!!not-base64!!!'))
        ->toThrow(\InvalidArgumentException::class);
});

it('throws on device with missing AES key', function () {
    $device = Device::factory()->lora()->create(['lora_aes_key' => null]);

    expect(fn () => $this->service->decrypt($device, 1, base64_encode('test')))
        ->toThrow(\RuntimeException::class);
});
