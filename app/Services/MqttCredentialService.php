<?php

namespace App\Services;

use App\Models\Device;
use App\Services\EmqxService;
use Illuminate\Support\Facades\Log;

class MqttCredentialService
{

    /**
     * Ensure the device has mqtt credentials. Returns ['username' => ..., 'password' => ..., 'created' => bool]
     */
    public function createCredentials(Device $device): array
    {
        if ($device->mqtt_username && $device->mqtt_password) {
            return [
                'mqtt_broker_url' => config('services.emqx.url', '/'),
                'username' => $device->mqtt_username,
                'password' => $device->mqtt_password,
                'created' => false,
            ];
        }

        $username = $device->uuid;
        $password = bin2hex(random_bytes(16));


        // Resolve EmqxService from the container so it can be mocked in tests
        $emqx = app(EmqxService::class);

        try {
            $result = $emqx->createUser($username, $password);
        } catch (\Throwable $e) {
            Log::error('MQTT credential creation failed: EMQX service error', [
                'device_id' => $device->id,
                'device_uuid' => $device->uuid,
                'error' => $e->getMessage(),
            ]);
            // Broker unreachable or other error: do not create credentials
            return [
                'mqtt_broker_url' => config('services.emqx.url', '/'),
                'created' => false,
                'error' => 'emqx_unavailable',
            ];
        }

        // EmqxService returns an array with 'status' on failure; treat that as failure
        if (is_array($result) && array_key_exists('status', $result)) {
            Log::error('MQTT credential creation failed: EMQX returned error response', [
                'device_id' => $device->id,
                'device_uuid' => $device->uuid,
                'emqx_response' => $result,
            ]);
            return [
                'mqtt_broker_url' => config('services.emqx.url', '/'),
                'created' => false,
                'error' => 'emqx_unavailable',
            ];
        }

        $device->update([
            'mqtt_username' => $username,
            'mqtt_password' => $password,
        ]);

        return [
            'mqtt_broker_url' => config('services.emqx.url', '/'),
            'username' => $username,
            'password' => $password,
            'created' => true,
        ];
    }
}
