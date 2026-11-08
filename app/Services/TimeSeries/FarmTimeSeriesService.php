<?php

namespace App\Services\TimeSeries;

use App\Enums\SensorType;
use App\Enums\TimeRange;
use App\Models\Farm;
use App\Services\InfluxDBService;
use Carbon\Carbon;

class FarmTimeSeriesService
{
    /** Bucket size of the 24 h trends and the moisture chart. */
    private const TREND_WINDOW = '1h';

    public function __construct(private readonly InfluxDBService $influx) {}

    // Round numeric values consistently for timeseries output
    private function roundValue(mixed $val, int $decimals = 2): mixed
    {
        if ($val === null) {
            return null;
        }
        if (is_numeric($val)) {
            return round((float) $val, $decimals);
        }

        return $val;
    }

    /**
     * Aggregated reading statistics per sensor type for a farm's history.
     *
     * Filters on the farm tag each reading was written with (ADR-0008), so the history keeps
     * readings from devices that have since moved away or been deleted, and leaves out readings
     * the farm's current devices took elsewhere. Only types with readings in the range appear.
     *
     * @return array{readingStatsByType: array<string, array{avgReading: mixed, minReading: mixed, maxReading: mixed}>}
     */
    public function farmStats(Farm $farm, string $range = '-24h'): array
    {
        $typeSet = implode(', ', array_map(fn (string $type) => "\"{$type}\"", SensorType::farmRelevantValues()));

        $base = <<<FLUX
                |> range(start: {$range})
                |> filter(fn: (r) => r._measurement == "sensor_measurement" and r._field == "value" and r.farm_id == "{$farm->id}" and contains(value: r.sensor_type, set: [{$typeSet}]))
                |> group(columns: ["sensor_type"])
                FLUX;

        $readingStatsByType = [];
        $aggregates = ['avgReading' => 'mean', 'minReading' => 'min', 'maxReading' => 'max'];

        try {
            foreach ($aggregates as $statKey => $fluxFunction) {
                foreach ($this->influx->queryPipeline($base."\n|> {$fluxFunction}()") as $table) {
                    foreach (($table->records ?? []) as $record) {
                        $type = $record['sensor_type'] ?? null;
                        if ($type === null) {
                            continue;
                        }
                        $readingStatsByType[$type] ??= array_fill_keys(array_keys($aggregates), null);
                        $readingStatsByType[$type][$statKey] = $this->roundValue($record->getValue(), 2);
                    }
                }
            }
        } catch (\Throwable $e) {
            // InfluxDB unavailable: return what was gathered so the page still renders
        }

        return [
            'readingStatsByType' => $readingStatsByType,
        ];
    }

    /**
     * Hourly mean per farm-relevant sensor type over the last 24 h, oldest first, for the tile sparklines.
     *
     * Filters on the farm tag each reading was written with (ADR-0008), like farmStats().
     * Only types with readings in the window appear.
     *
     * @return array<string, list<float>>
     */
    public function readingTrends(Farm $farm): array
    {
        $typeSet = implode(', ', array_map(fn (string $type) => "\"{$type}\"", SensorType::farmRelevantValues()));
        $range = TimeRange::DAY->value;
        $every = self::TREND_WINDOW;

        $pipeline = <<<FLUX
                |> range(start: {$range})
                |> filter(fn: (r) => r._measurement == "sensor_measurement" and r._field == "value" and r.farm_id == "{$farm->id}" and contains(value: r.sensor_type, set: [{$typeSet}]))
                |> group(columns: ["sensor_type"])
                |> aggregateWindow(every: {$every}, fn: mean, createEmpty: false)
                FLUX;

        $pointsByType = [];
        foreach ($this->hourlyPoints($pipeline, 'sensor_type') as [$type, $time, $value]) {
            if ($type !== null) {
                $pointsByType[$type][$time] = $value;
            }
        }

        return array_map(function (array $points): array {
            ksort($points);

            return array_values($points);
        }, $pointsByType);
    }

    /**
     * Hourly mean soil moisture over the last 24 h, one series per zone, for the Farm detail chart.
     *
     * Readings are grouped by the zone tag they were written with (ADR-0009). Each existing zone
     * with readings gets a series, in zone-name order, followed by a "No zone" series for readings
     * taken outside any zone. Readings of deleted zones are dropped, as they have no name to show.
     * A farm without zones gets one "Farm average" series of all its moisture readings.
     *
     * @return list<array{zoneId: int|null, name: string, data: list<array{x: int, y: float}>}>
     */
    public function moistureSeriesByZone(Farm $farm): array
    {
        $zoneNames = $farm->zones()->orderBy('name')->orderBy('id')->pluck('name', 'id');
        $range = TimeRange::DAY->value;
        $every = self::TREND_WINDOW;
        $moisture = SensorType::MOISTURE->value;
        $groupColumns = $zoneNames->isEmpty() ? '' : '"zone_id"';

        $pipeline = <<<FLUX
                |> range(start: {$range})
                |> filter(fn: (r) => r._measurement == "sensor_measurement" and r._field == "value" and r.farm_id == "{$farm->id}" and r.sensor_type == "{$moisture}")
                |> group(columns: [{$groupColumns}])
                |> aggregateWindow(every: {$every}, fn: mean, createEmpty: false)
                FLUX;

        $pointsByZone = [];
        foreach ($this->hourlyPoints($pipeline, 'zone_id') as [$zoneTag, $time, $value]) {
            $pointsByZone[$zoneTag ?? ''][$time] = $value;
        }

        $series = static function (?int $zoneId, string $name, array $points): array {
            ksort($points);

            return [
                'zoneId' => $zoneId,
                'name' => $name,
                'data' => array_map(fn (int $time, float $value) => ['x' => $time, 'y' => $value], array_keys($points), array_values($points)),
            ];
        };

        if ($zoneNames->isEmpty()) {
            return isset($pointsByZone['']) ? [$series(null, 'Farm average', $pointsByZone[''])] : [];
        }

        $out = [];
        foreach ($zoneNames as $zoneId => $name) {
            if (isset($pointsByZone[(string) $zoneId])) {
                $out[] = $series($zoneId, $name, $pointsByZone[(string) $zoneId]);
            }
        }
        if (isset($pointsByZone[''])) {
            $out[] = $series(null, 'No zone', $pointsByZone['']);
        }

        return $out;
    }

    /**
     * Runs an aggregated pipeline and yields each point as [tag value, time in ms, rounded value].
     * Yields nothing when InfluxDB is unavailable, so the page still renders.
     *
     * @return iterable<array{0: string|null, 1: int, 2: float}>
     */
    private function hourlyPoints(string $pipeline, string $tag): iterable
    {
        try {
            $tables = $this->influx->queryPipeline($pipeline);
        } catch (\Throwable) {
            return;
        }

        foreach ($tables as $table) {
            foreach (($table->records ?? []) as $record) {
                $value = $record->getValue();
                $time = $record->getTime();
                if ($value === null || $time === null) {
                    continue;
                }
                $tagValue = $record[$tag] ?? null;

                yield [
                    $tagValue === null || $tagValue === '' ? null : (string) $tagValue,
                    Carbon::parse($time)->getTimestampMs(),
                    (float) $this->roundValue($value, 2),
                ];
            }
        }
    }
}
