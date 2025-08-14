<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'farm' => $this->whenLoaded('farm'),
            'lat' => $this->lat,
            'lon' => $this->lon,
            'last_reading' => $this->last_reading,
            'last_reading_at' => $this->last_reading_at,
        ];
    }

    public function flat(Request $request)
    {
        return $this->toArray($request);
    }
}
