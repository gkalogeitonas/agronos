<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\FarmStatus;
use App\Enums\SensorType;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FarmOverviewService
{
    /**
     * Health summary per farm, derived from the farm's sensors and the devices they belong to.
     *
     * Counts and averages are aggregated in SQL, so the cost does not grow with the number of sensors.
     *
     * @param  EloquentCollection<int, Farm>  $farms
     * @return Collection<int, array{id: int, name: string, location: string|null, size: float|null, center: array{lng: float, lat: float}|null, status: string, statusLabel: string, sensors: int, devicesOnline: int, devicesTotal: int, moisture: float|null, temperature: float|null}>
     */
    public function summarize(EloquentCollection $farms): Collection
    {
        $farms->loadCount('sensors');
        $farms->loadAggregate([
            'sensors as moisture_avg' => fn (Builder $query) => $query->where('type', SensorType::MOISTURE->value),
            'sensors as temperature_avg' => fn (Builder $query) => $query->where('type', SensorType::TEMPERATURE->value),
        ], 'last_reading', 'avg');

        $deviceCounts = $this->deviceCounts($farms->modelKeys());

        return $farms->map(function (Farm $farm) use ($deviceCounts): array {
            $devicesTotal = (int) ($deviceCounts[$farm->id]->total ?? 0);
            $devicesOnline = (int) ($deviceCounts[$farm->id]->online ?? 0);
            $status = FarmStatus::fromDeviceCounts($devicesOnline, $devicesTotal);

            return [
                'id' => $farm->id,
                'name' => $farm->name,
                'location' => $farm->location,
                'size' => $farm->size !== null ? (float) $farm->size : null,
                'center' => $farm->center,
                'status' => $status->value,
                'statusLabel' => FarmStatus::labels()[$status->value],
                'sensors' => $farm->sensors_count,
                'devicesOnline' => $devicesOnline,
                'devicesTotal' => $devicesTotal,
                'moisture' => $this->roundReading($farm->moisture_avg),
                'temperature' => $this->roundReading($farm->temperature_avg),
            ];
        })->values();
    }

    /**
     * Distinct devices per farm (through the farm's sensors) and how many of them are online.
     *
     * The farm ids come from an already tenant-scoped query, so the join needs no further scoping.
     *
     * @param  array<int, int>  $farmIds
     * @return Collection<int, object{farm_id: int, total: int, online: int}>
     */
    private function deviceCounts(array $farmIds): Collection
    {
        if ($farmIds === []) {
            return collect();
        }

        return DB::table('sensors')
            ->join('devices', 'devices.id', '=', 'sensors.device_id')
            ->whereIn('sensors.farm_id', $farmIds)
            ->groupBy('sensors.farm_id')
            ->select('sensors.farm_id')
            ->selectRaw('COUNT(DISTINCT devices.id) AS total')
            ->selectRaw('COUNT(DISTINCT CASE WHEN devices.status = ? THEN devices.id END) AS online', [DeviceStatus::ONLINE->value])
            ->get()
            ->keyBy('farm_id');
    }

    private function roundReading(float|string|null $average): ?float
    {
        return $average === null ? null : round((float) $average, 1);
    }
}
