<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\SensorType;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class FarmOverviewService
{
    /**
     * Health summary per farm, derived from the farm's sensors and the devices they belong to.
     *
     * Status is OK when every device is online, Offline when none are, Warning otherwise,
     * and Unknown for a farm without devices.
     *
     * @param  EloquentCollection<int, Farm>  $farms
     * @return Collection<int, array{id: int, name: string, location: string|null, size: float|null, center: array{lng: float, lat: float}|null, status: string, sensors: int, devicesOnline: int, devicesTotal: int, moisture: float|null, temperature: float|null}>
     */
    public function summarize(EloquentCollection $farms): Collection
    {
        $farms->loadMissing('sensors.device');

        return $farms->map(function (Farm $farm): array {
            $devices = $farm->sensors->pluck('device')->filter()->unique('id');
            $devicesOnline = $devices->filter(fn ($device) => $device->status === DeviceStatus::ONLINE)->count();

            return [
                'id' => $farm->id,
                'name' => $farm->name,
                'location' => $farm->location,
                'size' => $farm->size !== null ? (float) $farm->size : null,
                'center' => $farm->center,
                'status' => $this->status($devicesOnline, $devices->count()),
                'sensors' => $farm->sensors->count(),
                'devicesOnline' => $devicesOnline,
                'devicesTotal' => $devices->count(),
                'moisture' => $this->averageReading($farm, SensorType::MOISTURE),
                'temperature' => $this->averageReading($farm, SensorType::TEMPERATURE),
            ];
        })->values();
    }

    private function status(int $online, int $total): string
    {
        return match (true) {
            $total === 0 => 'unknown',
            $online === $total => 'ok',
            $online === 0 => 'offline',
            default => 'warning',
        };
    }

    private function averageReading(Farm $farm, SensorType $type): ?float
    {
        $readings = $farm->sensors
            ->filter(fn ($sensor) => $sensor->type === $type->value && is_numeric($sensor->last_reading))
            ->pluck('last_reading');

        return $readings->isEmpty() ? null : round((float) $readings->avg(), 1);
    }
}
