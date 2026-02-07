<?php

namespace App\Services\TimeSeries;

use App\Services\InfluxDBService;
use Carbon\Carbon;

class SensorTimeSeriesService
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
     * Get recent readings for a sensor, flattened to [{time, value}]
     */
    public function recentReadings(int $sensorId, string $range = '-7d', int $limit = 10): array
    {
        $sensorIdStr = (string) $sensorId;
        $pipeline = <<<FLUX
                    |> range(start: {$range})
                    |> filter(fn: (r) => r._measurement == "sensor_measurement" and r.sensor_id == "{$sensorIdStr}" and r._field == "value")
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

    /**
     * Compute min/max/avg/count for a sensor over a range.
     */
    public function stats(int $sensorId, string $range = '-24h'): array
    {
        $sensorIdStr = (string) $sensorId;
        $base = <<<FLUX
                |> range(start: {$range})
                |> filter(fn: (r) => r._measurement == "sensor_measurement" and r.sensor_id == "{$sensorIdStr}" and r._field == "value")
                FLUX;

        $stats = ['min' => null, 'max' => null, 'avg' => null, 'count' => 0];

        try {
            // min
            $minRes = $this->influx->queryPipeline($base."\n|> min()");
            foreach ($minRes as $t) {
                foreach (($t->records ?? []) as $rec) {
                    $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                    if ($val !== null) {
                        $stats['min'] = $this->roundValue($val, 2);
                        break 2;
                    }
                }
            }
            // max
            $maxRes = $this->influx->queryPipeline($base."\n|> max()");
            foreach ($maxRes as $t) {
                foreach (($t->records ?? []) as $rec) {
                    $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                    if ($val !== null) {
                        $stats['max'] = $this->roundValue($val, 2);
                        break 2;
                    }
                }
            }
            // mean
            $meanRes = $this->influx->queryPipeline($base."\n|> mean()");
            foreach ($meanRes as $t) {
                foreach (($t->records ?? []) as $rec) {
                    $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                    if ($val !== null) {
                        $stats['avg'] = $this->roundValue($val, 2);
                        break 2;
                    }
                }
            }
            // count
            $countRes = $this->influx->queryPipeline($base."\n|> count()");
            foreach ($countRes as $t) {
                foreach (($t->records ?? []) as $rec) {
                    $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                    if ($val !== null) {
                        $stats['count'] = (int) $val;
                        break 2;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore and return defaults
        }

        // Progressive widening if nothing found — use TimeRange enum values
        if (($stats['count'] ?? 0) === 0) {
            if ($range === \App\Enums\TimeRange::DAY->value) {
                return $this->stats($sensorId, \App\Enums\TimeRange::WEEK->value);
            }
            if ($range === \App\Enums\TimeRange::WEEK->value) {
                return $this->stats($sensorId, \App\Enums\TimeRange::MONTH->value);
            }
        }

        return $stats;
    }

    public function chartReadings(int $sensorId, string $range = '-24h'): array
    {
        $sensorIdStr = (string) $sensorId;

        // Determine aggregate window based on requested range via TimeRange enum
        $every = '15m';
        try {
            $tr = \App\Enums\TimeRange::tryFrom($range);
            if ($tr !== null) {
                $every = $tr->aggregateWindow();
            }
        } catch (\Throwable) {
            // fallback to default
        }

        $pipeline = <<<FLUX
                |> range(start: {$range})
                |> filter(fn: (r) => r._measurement == "sensor_measurement" and r.sensor_id == "{$sensorIdStr}" and r._field == "value")
                |> aggregateWindow(every: {$every}, fn: mean, createEmpty: false)
                |> sort(columns: ["_time"])
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
                // Extract time (support getTime() if provided)
                $time = method_exists($rec, 'getTime') ? $rec->getTime() : ($rec->_time ?? ($rec['time'] ?? null));

                if ($time instanceof \DateTimeInterface) {
                    $ms = (int) ($time->getTimestamp() * 1000);
                } elseif (is_string($time) || is_numeric($time)) {
                    try {
                        $ms = (int) (Carbon::parse($time)->timestamp * 1000);
                    } catch (\Throwable $e) {
                        // skip unparseable times
                        continue;
                    }
                } else {
                    // unknown time format, skip
                    continue;
                }

                $rawValue = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                if ($rawValue === null) {
                    continue;
                }

                $value = $this->roundValue($rawValue, 2);

                $out[] = [$ms, $value];
            }
        }

        // Ensure ascending order by timestamp
        usort($out, fn ($a, $b) => $a[0] <=> $b[0]);

        return $out;
    }
}
