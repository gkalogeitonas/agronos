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

        if (! $this->provisionBrokerUser($username, $password, $topicBase, ['device_id' => $device->id, 'device_uuid' => $device->uuid])) {
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
     * Create or update an EMQX user and its rules for a device or gateway, returning whether both succeeded.
     *
     * @param  array<string, mixed>  $logContext  Identifies the owner of the user in failure logs
     */
    public function provisionBrokerUser(string $username, string $password, string $topicBase, array $logContext = []): bool
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
                ...$logContext,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (is_array($result) && array_key_exists('status', $result)) {
            Log::error('MQTT credential provisioning failed: EMQX returned error response', [
                ...$logContext,
                'emqx_response' => $result,
            ]);

            return false;
        }

        return true;
    }
}
