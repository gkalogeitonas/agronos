<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Enums\SensorType;
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
        // Decode the inner JSON payload forwarded by the gateway
        $innerPayload = json_decode($request->validated()['payload'], true);
        if (! is_array($innerPayload)) {
            return response()->json(['message' => 'Invalid inner payload JSON.'], 422);
        }

        // Validate the inner payload structure
        $innerValidator = Validator::make($innerPayload, [
            'device_id' => ['required', 'string', 'max:255'],
            'fcnt' => ['required', 'integer', 'min:0'],
            'payload' => ['required', 'string'],
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

        // Look up the LoRa device by UUID
        $device = Device::allTenants()
            ->where('uuid', $validated['device_id'])
            ->where('type', DeviceType::LORA->value)
            ->first();

        if (! $device) {
            return response()->json(['message' => 'LoRa device not found.'], 404);
        }

        // Validate frame counter (anti-replay)
        try {
            $crypto->validateFrameCounter($device, $validated['fcnt']);
        } catch (LoRaReplayException $e) {
            Log::warning('LoRa replay attack blocked', [
                'device_uuid' => $device->uuid,
                'incoming_fcnt' => $validated['fcnt'],
                'stored_fcnt' => $device->lora_frame_counter,
            ]);

            return response()->json(['message' => 'Replay detected.'], 409);
        } catch (LoRaFrameCounterGapException $e) {
            Log::warning('LoRa frame counter gap exceeded', [
                'device_uuid' => $device->uuid,
                'incoming_fcnt' => $validated['fcnt'],
                'stored_fcnt' => $device->lora_frame_counter,
            ]);

            return response()->json(['message' => 'Frame counter gap exceeded.'], 422);
        }

        // Decrypt the payload
        try {
            $rawBytes = $crypto->decrypt($device, $validated['fcnt'], $validated['payload']);
        } catch (\Throwable $e) {
            Log::error('LoRa decryption failed', [
                'device_uuid' => $device->uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Decryption failed.'], 422);
        }

        // Deserialize binary into sensor readings
        try {
            $readings = $crypto->deserialize($rawBytes);
        } catch (\InvalidArgumentException $e) {
            Log::error('LoRa deserialization failed', [
                'device_uuid' => $device->uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Payload deserialization failed.'], 422);
        }

        // Update device status only; gateway metrics are not stored on device
        $device->update([
            'status' => DeviceStatus::ONLINE,
            'last_seen_at' => now(),
        ]);

        // Map deserialized readings to the device's pre-registered sensors by type
        $typeToValue = [
            SensorType::TEMPERATURE->value => $readings['temperature'],
            SensorType::HUMIDITY->value => $readings['humidity'],
            SensorType::MOISTURE->value => $readings['moisture'],
            SensorType::BATTERY->value => $readings['battery'],
        ];

        $sensors = $device->sensors()->get();
        $sensorPayloads = [];
        foreach ($sensors as $sensor) {
            if (isset($typeToValue[$sensor->type])) {
                $sensorPayloads[] = [
                    'uuid' => $sensor->uuid,
                    'value' => $typeToValue[$sensor->type],
                ];
            }
        }

        $response = $sensorDataService->processSensorData($device, $sensorPayloads);

        return response()->json(['message' => 'LoRa data processed.'] + $response, 200);
    }
}
