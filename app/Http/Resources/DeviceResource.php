<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Device summary for list views, matching the `Device` frontend type.
 * Includes the device's farm names when `sensors.farm` is loaded.
 *
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'status' => $this->status,
            'signal_strength' => $this->signal_strength,
            'battery_level' => $this->battery_level,
            'last_seen_at' => $this->last_seen_at,
            'farms' => $this->whenLoaded('sensors', fn () => $this->linkedFarms()->pluck('name')->values()),
        ];
    }
}
