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
        // Get sensor counts and types from database - much more efficient
        $totalSensors = $farm->sensors()->count();
        $sensorTypeStats = $farm->sensors()
            ->selectRaw('type, COUNT(*) as count')
            ->whereNotNull('type')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        if ($totalSensors === 0) {
            return [
                'totalSensors' => 0,
                'activeSensors' => 0,
                'totalReadings' => 0,
                'sensorTypeStats' => [],
                'readingStatsByType' => [],
            ];
        }

        $sensorsByType = $farm->sensors()->get()->groupBy('type');
        $readingStatsByType = [];
        $totalReadings = 0;
        $activeSensors = 0;

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
                'count' => count($sensorIds),
                'activeSensors' => 0,
                'avgReading' => null,
                'minReading' => null,
                'maxReading' => null,
                'totalReadings' => 0,
            ];

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

                $countRes = $this->influx->queryPipeline($base."\n|> count()");
                foreach ($countRes as $t) {
                    foreach (($t->records ?? []) as $rec) {
                        $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                        if ($val !== null) {
                            $typeStats['totalReadings'] = (int) $val;
                            break 2;
                        }
                    }
                }

                // Count active sensors for this type
                $activeSensorRes = $this->influx->queryPipeline($base."\n|> group(columns: [\"sensor_id\"])\n|> count()\n|> group()");
                $activeSensorCount = 0;
                foreach ($activeSensorRes as $t) {
                    $activeSensorCount += count($t->records ?? []);
                }
                $typeStats['activeSensors'] = $activeSensorCount;

            } catch (\Throwable $e) {
                // ignore and keep defaults for this type
            }

            $readingStatsByType[$type] = $typeStats;
            $totalReadings += $typeStats['totalReadings'];
            $activeSensors += $typeStats['activeSensors'];
        }

        // Progressive widening if no data found and range is too narrow
        if ($totalReadings === 0 && $range === '-24h') {
            return $this->farmStats($farm, '-7d');
        }
        if ($totalReadings === 0 && $range === '-7d') {
            return $this->farmStats($farm, '-30d');
        }

        return [
            'totalSensors' => $totalSensors,
            'activeSensors' => $activeSensors,
            'totalReadings' => $totalReadings,
            'sensorTypeStats' => $sensorTypeStats,
            'readingStatsByType' => $readingStatsByType,
        ];
    }

    /**
     * Get recent readings across all sensors on a farm
     */
    public function farmRecentReadings(Farm $farm, string $range = '-24h', int $limit = 20): array
    {
        $sensorIds = $farm->sensors()->pluck('id')->map(fn ($id) => (string) $id)->toArray();

        if (empty($sensorIds)) {
            return [];
        }

        $sensorFilter = implode(' or ', array_map(fn ($id) => "r.sensor_id == \"{$id}\"", $sensorIds));

        $pipeline = <<<FLUX
                    |> range(start: {$range})
                    |> filter(fn: (r) => r._measurement == "sensor_measurement" and ({$sensorFilter}) and r._field == "value")
                    |> sort(columns: ["_time"], desc: true)
                    |> limit(n: {$limit})
                    FLUX;

        try {
            $result = $this->influx->queryPipeline($pipeline);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($result as $table) {
            $records = $table->records ?? [];
            foreach ($records as $rec) {
                // Normalize time to match DB format: "Y-m-d H:i:s"
                $time = method_exists($rec, 'getTime') ? $rec->getTime() : ($rec->_time ?? ($rec['time'] ?? null));
                if ($time instanceof \DateTimeInterface) {
                    $time = $time->format('Y-m-d H:i:s');
                } elseif (is_string($time)) {
                    try {
                        $dt = new \DateTime($time);
                        $time = $dt->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        // leave original string if parsing fails
                    }
                }

                $rawValue = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                $value = $this->roundValue($rawValue, 2);

                $out[] = [
                    'time' => $time,
                    'value' => $value,
                ];
            }
        }

        return $out;
    }
}
