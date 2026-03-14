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
     * Deserialize a variable-length binary payload into sensor readings.
     *
     * Layout: N × 6-byte records, where each record is:
     *   Bytes 0-3: first 4 characters of the sensor UUID (ASCII)
     *   Bytes 4-5: sensor value (int16 LE, scaled ×100)
     *
     * @return array<string, float>  Keyed by 4-char UUID prefix, value divided by 100
     */
    public function deserialize(string $binary): array
    {
        $len = strlen($binary);
        if ($len < 6 || $len % 6 !== 0) {
            throw new \InvalidArgumentException(
                "Binary payload length must be a non-zero multiple of 6 bytes, got {$len}."
            );
        }

        $readings = [];
        $count = $len / 6;
        for ($i = 0; $i < $count; $i++) {
            $chunk = substr($binary, $i * 6, 6);
            $prefix = substr($chunk, 0, 4);
            $raw = unpack('v1v', substr($chunk, 4, 2))['v'];
            // Convert unsigned int16 to signed int16 (values can be negative)
            if ($raw >= 0x8000) {
                $raw -= 0x10000;
            }
            $readings[$prefix] = round($raw / 100, 2);
        }

        return $readings;
    }
}
