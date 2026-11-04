<?php

namespace App\Http\Resources;

use App\Enums\SensorType;
use App\Models\Sensor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sensor with its device and, when `device.farm` is loaded, its device's farm
 * (null while the device is unassigned). A sensor has no farm of its own (ADR-0008).
 *
 * @mixin Sensor
 */
class SensorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'device' => $this->whenLoaded('device'),
            'farm' => $this->when(
                $this->relationLoaded('device') && $this->device?->relationLoaded('farm'),
                fn () => $this->device?->farm?->only(['id', 'name', 'coordinates']),
            ),
            'lat' => $this->lat,
            'lon' => $this->lon,
            'last_reading' => $this->last_reading,
            'last_reading_at' => $this->last_reading_at,
            'unit' => $this->type && SensorType::tryFrom($this->type) ? SensorType::tryFrom($this->type)->unit() : '',
        ];
    }

    public function flat(Request $request)
    {
        return $this->toArray($request);
    }
}
