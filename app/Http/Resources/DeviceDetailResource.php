<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;

/**
 * Device summary plus the fields of the device page, matching the `DeviceDetail` frontend type.
 * Returns the MQTT credentials, because the owner reads them on the device page.
 *
 * @mixin Device
 */
class DeviceDetailResource extends DeviceResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'created_at' => $this->created_at,
            'lora_frame_counter' => $this->lora_frame_counter,
            'mqtt_username' => $this->mqtt_username,
            'mqtt_password' => $this->mqtt_password,
        ];
    }
}
