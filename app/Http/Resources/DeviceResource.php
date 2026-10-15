<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Device summary for list views. Expects `sensors.farm` to be loaded.
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
            ...$this->resource->only(['id', 'name', 'uuid', 'type', 'status', 'signal_strength', 'battery_level', 'last_seen_at']),
            'farms' => $this->linkedFarms()->pluck('name')->values(),
        ];
    }
}
