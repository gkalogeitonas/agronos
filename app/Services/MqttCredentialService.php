<?php

namespace App\Services;

use App\Models\Device;
use App\Services\EmqxService;

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


        $emqx = new EmqxService();
        $emqx->createUser($username, $password);

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

    public function rotateCredentials(Device $device): array
    {
        $username = $device->mqtt_username ?: $device->uuid;
        $password = bin2hex(random_bytes(16));

        // update EMQX password
        app('emqx')->updateUserPassword($username, $password);

        $device->update([
            'mqtt_username' => $username,
            'mqtt_password' => $password,
        ]);

        return [
            'username' => $username,
            'password' => $password,
            'rotated' => true,
        ];
    }
}
