<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Exceptions\LoRaFrameCounterGapException;
use App\Exceptions\LoRaReplayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoRaWebhookRequest;
use App\Models\Device;
use App\Services\LoRaCryptoService;
use App\Services\SensorDataService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LoRaDataController extends Controller
{
    public function webhook(
        LoRaWebhookRequest $request,
        LoRaCryptoService $crypto,
        SensorDataService $sensorDataService,
    ) {
        // Decode the gateway JSON from the EMQX envelope
        $gatewayPayload = json_decode($request->validated()['request']['payload'], true);
        if (! is_array($gatewayPayload)) {
            return response()->json(['message' => 'Invalid inner payload JSON.'], 422);
        }



        // Validate the gateway JSON structure
        $innerValidator = Validator::make($gatewayPayload, [
            'gateway_id' => ['required', 'string', 'max:255'],
            'raw_payload' => ['required', 'string', 'regex:/^[0-9A-Fa-f]+$/'],
            'rssi' => ['nullable', 'integer'],
            'snr' => ['nullable', 'numeric'],
        ]);


        if ($innerValidator->fails()) {
            return response()->json([
                'message' => 'Invalid LoRa payload.',
                'errors' => $innerValidator->errors(),
            ], 422);
        }

        $validated = $innerValidator->validated();

        // Decode hex → binary and extract the embedded device ID, frame counter, and ciphertext
        // Layout: [device_id: 4B LE][fcnt: 4B LE][encrypted payload: N×6B]
        $rawBytes = hex2bin($validated['raw_payload']);
        if ($rawBytes === false || strlen($rawBytes) < 8 || (strlen($rawBytes) - 8) % 6 !== 0) {
            return response()->json(['message' => 'Invalid raw_payload structure.'], 422);
        }

        //log the incoming webhook for debugging
        Log::info('Received LoRa webhook', [
            'username' => $request->validated()['request']['username'],
            'gateway_payload' => $validated,
        ]);

        $deviceId = unpack('V', substr($rawBytes, 0, 4))[1];
        $fcnt = unpack('V', substr($rawBytes, 4, 4))[1];
        $ciphertext = base64_encode(substr($rawBytes, 8));

        //log the extracted values for debugging
        Log::debug('Extracted LoRa payload components', [
            'device_id' => $deviceId,
            'frame_counter' => $fcnt,
            'ciphertext_length' => strlen($ciphertext),
        ]);

        // Look up the LoRa device by its embedded numeric ID
        $device = Device::allTenants()
            ->where('id', $deviceId)
            ->where('type', DeviceType::LORA->value)
            ->first();

        if (! $device) {
            return response()->json(['message' => 'LoRa device not found.'], 404);
        }

        // Validate frame counter (anti-replay)
        try {
            $crypto->validateFrameCounter($device, $fcnt);
        } catch (LoRaReplayException $e) {
            Log::warning('LoRa replay attack blocked', [
                'device_id' => $device->id,
                'incoming_fcnt' => $fcnt,
                'stored_fcnt' => $device->lora_frame_counter,
            ]);

            return response()->json(['message' => 'Replay detected.'], 409);
        } catch (LoRaFrameCounterGapException $e) {
            Log::warning('LoRa frame counter gap exceeded', [
                'device_id' => $device->id,
                'incoming_fcnt' => $fcnt,
                'stored_fcnt' => $device->lora_frame_counter,
            ]);

            return response()->json(['message' => 'Frame counter gap exceeded.'], 422);
        }

        // Decrypt the payload
        try {
            $rawBinaryDecrypted = $crypto->decrypt($device, $fcnt, $ciphertext);
        } catch (\Throwable $e) {
            Log::error('LoRa decryption failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Decryption failed.'], 422);
        }

        // Deserialize binary into sensor readings
        try {
            $readings = $crypto->deserialize($rawBinaryDecrypted);
        } catch (\InvalidArgumentException $e) {
            Log::error('LoRa deserialization failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Payload deserialization failed.'], 422);
        }

        // Update device status only; gateway metrics are not stored on device
        $device->update([
            'status' => DeviceStatus::ONLINE,
            'last_seen_at' => now(),
        ]);

        // Map deserialized readings to the device's pre-registered sensors by UUID prefix
        $sensors = $device->sensors()->get();
        $sensorPayloads = [];
        foreach ($sensors as $sensor) {
            $prefix = substr($sensor->uuid, 0, 4);
            if (array_key_exists($prefix, $readings)) {
                $sensorPayloads[] = [
                    'uuid' => $sensor->uuid,
                    'value' => $readings[$prefix],
                ];
            }
        }

        $response = $sensorDataService->processSensorData($device, $sensorPayloads);

        return response()->json(['message' => 'LoRa data processed.'] + $response, 200);
    }
}
