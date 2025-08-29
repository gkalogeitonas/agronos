<?php

namespace App\Services\TimeSeries;

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
     * Get aggregated statistics for all sensors on a farm
     */
    public function farmStats(Farm $farm, string $range = '-24h'): array
    {
        $sensorsByType = $farm->sensors()->get()->groupBy('type');
        $readingStatsByType = [];

        if ($sensorsByType->isEmpty()) {
            return [
                'readingStatsByType' => [],
            ];
        }

        // Calculate stats per sensor type
        foreach ($sensorsByType as $type => $sensors) {
            if (! $type) {
                continue;
            } // Skip sensors without type

            $sensorIds = $sensors->pluck('id')->map(fn ($id) => (string) $id)->toArray();
            $sensorFilter = implode(' or ', array_map(fn ($id) => "r.sensor_id == \"{$id}\"", $sensorIds));

            $base = <<<FLUX
                    |> range(start: {$range})
                    |> filter(fn: (r) => r._measurement == "sensor_measurement" and ({$sensorFilter}) and r._field == "value")
                    FLUX;

            $typeStats = [
                'avgReading' => null,
                'minReading' => null,
                'maxReading' => null,
            ];

            //log base
           //dd("InfluxDB Query: {$base}");

            try {
                // Overall stats for this type
                $meanRes = $this->influx->queryPipeline($base."\n|> mean()");
                foreach ($meanRes as $t) {
                    foreach (($t->records ?? []) as $rec) {
                        $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                        if ($val !== null) {
                            $typeStats['avgReading'] = $this->roundValue($val, 2);
                            break 2;
                        }
                    }
                }

                $minRes = $this->influx->queryPipeline($base."\n|> min()");
                foreach ($minRes as $t) {
                    foreach (($t->records ?? []) as $rec) {
                        $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                        if ($val !== null) {
                            $typeStats['minReading'] = $this->roundValue($val, 2);
                            break 2;
                        }
                    }
                }

                $maxRes = $this->influx->queryPipeline($base."\n|> max()");
                foreach ($maxRes as $t) {
                    foreach (($t->records ?? []) as $rec) {
                        $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                        if ($val !== null) {
                            $typeStats['maxReading'] = $this->roundValue($val, 2);
                            break 2;
                        }
                    }
                }

            } catch (\Throwable $e) {
                // ignore and keep defaults for this type
            }

            $readingStatsByType[$type] = $typeStats;
        }

        return [
            'readingStatsByType' => $readingStatsByType,
        ];
    }
}
