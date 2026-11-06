<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\FarmHealth;
use App\Enums\SensorType;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FarmOverviewService
{
    /**
     * Health summary per farm, derived from the devices on the farm and their sensors.
     *
     * Counts and averages are aggregated in SQL, so the cost does not grow with the number of sensors.
     *
     * @param  EloquentCollection<int, Farm>  $farms
     * @return Collection<int, array{id: int, name: string, location: string|null, size: float|null, center: array{lng: float, lat: float}|null, health: string, healthLabel: string, sensors: int, devicesOnline: int, devicesTotal: int, openAlerts: int, moisture: float|null, temperature: float|null}>
     */
    public function summarize(EloquentCollection $farms): Collection
    {
        $farms->loadCount([
            'sensors' => fn (Builder $query) => $query->farmRelevant(),
            'alerts as open_alerts_count' => fn (Builder $query) => $query->open(),
        ]);
        $farms->loadAggregate([
            'sensors as moisture_avg' => fn (Builder $query) => $query->where('sensors.type', SensorType::MOISTURE->value),
            'sensors as temperature_avg' => fn (Builder $query) => $query->where('sensors.type', SensorType::TEMPERATURE->value),
        ], 'last_reading', 'avg');

        $deviceCounts = $this->deviceCounts($farms->modelKeys());

        return $farms->map(function (Farm $farm) use ($deviceCounts): array {
            $devicesTotal = (int) ($deviceCounts[$farm->id]->total ?? 0);
            $devicesOnline = (int) ($deviceCounts[$farm->id]->online ?? 0);
            $health = FarmHealth::fromDeviceCounts($devicesOnline, $devicesTotal);

            return [
                'id' => $farm->id,
                'name' => $farm->name,
                'location' => $farm->location,
                'size' => $farm->size !== null ? (float) $farm->size : null,
                'center' => $farm->center,
                'health' => $health->value,
                'healthLabel' => FarmHealth::labels()[$health->value],
                'sensors' => $farm->sensors_count,
                'devicesOnline' => $devicesOnline,
                'devicesTotal' => $devicesTotal,
                'openAlerts' => $farm->open_alerts_count,
                'moisture' => $this->roundReading($farm->moisture_avg),
                'temperature' => $this->roundReading($farm->temperature_avg),
            ];
        })->values();
    }

    /**
     * Devices per farm and how many of them are online.
     *
     * The farm ids come from an already tenant-scoped query, so the query needs no further scoping.
     *
     * @param  array<int, int>  $farmIds
     * @return Collection<int, object{farm_id: int, total: int, online: int}>
     */
    private function deviceCounts(array $farmIds): Collection
    {
        if ($farmIds === []) {
            return collect();
        }

        return DB::table('devices')
            ->whereIn('farm_id', $farmIds)
            ->groupBy('farm_id')
            ->select('farm_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS online', [DeviceStatus::ONLINE->value])
            ->get()
            ->keyBy('farm_id');
    }

    private function roundReading(float|string|null $average): ?float
    {
        return $average === null ? null : round((float) $average, 1);
    }
}
