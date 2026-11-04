<?php

namespace App\Services\TimeSeries;

use App\Enums\SensorType;
use App\Models\Farm;
use App\Services\InfluxDBService;

class FarmTimeSeriesService
{
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
}
