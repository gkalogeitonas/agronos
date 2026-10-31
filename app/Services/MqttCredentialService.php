<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class MqttCredentialService
{
    /**
     * Ensure the device has mqtt credentials and that its EMQX user and rules exist, even when the credentials
     * already existed (the dev seeder writes them without touching EMQX). On failure only the broker url,
     * created=false and an error are returned.
     *
     * @return array{mqtt_broker_url: string, username?: string, password?: string, topic_base?: string, created: bool, error?: string}
     */
    public function createCredentials(Device $device): array
    {
        $created = ! ($device->mqtt_username && $device->mqtt_password);
        $username = $created ? $device->uuid : $device->mqtt_username;
        $password = $created ? bin2hex(random_bytes(16)) : $device->mqtt_password;
        $topicBase = MqttTopics::deviceBase($device->uuid);

        if (! $this->provisionBrokerUser($device, $username, $password, $topicBase)) {
            return [
                'mqtt_broker_url' => config('services.emqx.url', '/'),
                'created' => false,
                'error' => 'emqx_unavailable',
            ];
        }

        if ($created) {
            $device->update([
                'mqtt_username' => $username,
                'mqtt_password' => $password,
            ]);
        }

        return [
            'mqtt_broker_url' => config('services.emqx.url', '/'),
            'username' => $username,
            'password' => $password,
            'topic_base' => $topicBase,
            'created' => $created,
        ];
    }

    /**
     * Create or update the device's EMQX user and its rules, returning whether both succeeded.
     */
    private function provisionBrokerUser(Device $device, string $username, string $password, string $topicBase): bool
    {
        // Resolve EmqxService from the container so it can be mocked in tests
        $emqx = app(EmqxService::class);

        try {
            $result = $emqx->createUser($username, $password);

            // EmqxService returns an array with 'status' on failure; treat that as failure
            if (! (is_array($result) && array_key_exists('status', $result))) {
                $result = $emqx->authorizeUser($username, $topicBase);
            }
        } catch (\Throwable $e) {
            Log::error('MQTT credential provisioning failed: EMQX service error', [
                'device_id' => $device->id,
                'device_uuid' => $device->uuid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (is_array($result) && array_key_exists('status', $result)) {
            Log::error('MQTT credential provisioning failed: EMQX returned error response', [
                'device_id' => $device->id,
                'device_uuid' => $device->uuid,
                'emqx_response' => $result,
            ]);

            return false;
        }

        return true;
    }
}
