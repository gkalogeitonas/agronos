<?php

namespace App\Services;

use App\Exceptions\LoRaFrameCounterGapException;
use App\Exceptions\LoRaReplayException;
use App\Models\Device;

class LoRaCryptoService
{
    public const MAX_FCNT_GAP = 10_000;

    /**
     * Validate the incoming frame counter against the device's stored counter.
     * Updates the device's counter on success.
     *
     * @throws LoRaReplayException
     * @throws LoRaFrameCounterGapException
     */
    public function validateFrameCounter(Device $device, int $incomingFcnt): void
    {
        $storedFcnt = (int) $device->lora_frame_counter;

        if ($incomingFcnt <= $storedFcnt) {
            throw new LoRaReplayException($incomingFcnt, $storedFcnt);
        }

        $gap = $incomingFcnt - $storedFcnt;
        if ($gap > self::MAX_FCNT_GAP) {
            throw new LoRaFrameCounterGapException($gap, self::MAX_FCNT_GAP);
        }

        $device->update(['lora_frame_counter' => $incomingFcnt]);
    }

    /**
     * Decrypt a Base64-encoded AES-128-CTR ciphertext using the device's LoRa key.
     *
     * Nonce layout (16 bytes):
     *   [4-byte device ID LE] [4-byte frame counter LE] [8-byte zero padding]
     */
    public function decrypt(Device $device, int $fcnt, string $base64Ciphertext): string
    {
        $ciphertext = base64_decode($base64Ciphertext, true);
        if ($ciphertext === false) {
            throw new \InvalidArgumentException('Invalid Base64 ciphertext.');
        }

        $key = hex2bin($device->lora_aes_key);
        if ($key === false || strlen($key) !== 16) {
            throw new \RuntimeException('Invalid AES key for device '.$device->uuid);
        }

        // Build deterministic 16-byte nonce
        $nonce = pack('V', $device->id)    // 4 bytes: device ID (little-endian uint32)
               .pack('V', $fcnt)          // 4 bytes: frame counter (little-endian uint32)
               .str_repeat("\x00", 8);    // 8 bytes: zero padding

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-128-ctr',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $nonce
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed for device '.$device->uuid);
        }

        return $plaintext;
    }

    /**
     * Deserialize a 7-byte binary payload into sensor readings.
     *
     * Layout:
     *   Bytes 0-1: temperature (int16 LE, scaled ×100) → divide by 100 for °C
     *   Bytes 2-3: humidity    (int16 LE, scaled ×100) → divide by 100 for %
     *   Bytes 4-5: moisture    (int16 LE, scaled ×100) → divide by 100 for %
     *   Byte  6:   battery     (uint8, 0–100%)
     *
     * @return array{temperature: float, humidity: float, moisture: float, battery: int}
     */
    public function deserialize(string $binary): array
    {
        if (strlen($binary) < 7) {
            throw new \InvalidArgumentException('Binary payload must be at least 7 bytes, got '.strlen($binary).'.');
        }

        $unpacked = unpack('vtemp/vhumidity/vmoisture/Cbattery', $binary);

        // Convert unsigned int16 to signed int16 for temperature (can be negative)
        $temp = $unpacked['temp'];
        if ($temp >= 0x8000) {
            $temp -= 0x10000;
        }

        $humidity = $unpacked['humidity'];
        if ($humidity >= 0x8000) {
            $humidity -= 0x10000;
        }

        $moisture = $unpacked['moisture'];
        if ($moisture >= 0x8000) {
            $moisture -= 0x10000;
        }

        return [
            'temperature' => round($temp / 100, 2),
            'humidity' => round($humidity / 100, 2),
            'moisture' => round($moisture / 100, 2),
            'battery' => $unpacked['battery'],
        ];
    }
}
