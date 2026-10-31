<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Gateway;
use Illuminate\Support\Facades\Log;

class MqttCredentialService
{
    /**
     * A new random broker password for a device or gateway.
     */
    public static function generatePassword(): string
    {
        return bin2hex(random_bytes(16));
    }

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
        $password = $created ? self::generatePassword() : $device->mqtt_password;
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
     * Create or update the gateway's EMQX user with the given password, and its rules, returning whether both
     * succeeded. The gateway may not be saved yet; the caller stores the password only on success.
     */
    public function provisionGateway(Gateway $gateway, string $password): bool
    {
        return $this->provisionBrokerUser($gateway->mqtt_username, $password, $gateway->topicBase(), ['gateway_username' => $gateway->mqtt_username]);
    }

    /**
     * Write the user's rules and then the user, returning whether both succeeded. Rules come first because they
     * grant nothing without a user, so a failed user write leaves the previous password valid and still matching
     * what Agronos stores.
     *
     * @param  array<string, mixed>  $logContext  Identifies the owner of the user in failure logs
     */
    private function provisionBrokerUser(string $username, string $password, string $topicBase, array $logContext): bool
    {
        // Resolve EmqxService from the container so it can be mocked in tests
        $emqx = app(EmqxService::class);

        try {
            $result = $emqx->authorizeUser($username, $topicBase);

            // EmqxService returns an array with 'status' on failure; treat that as failure
            if ($result === true) {
                $result = $emqx->createUser($username, $password);
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
