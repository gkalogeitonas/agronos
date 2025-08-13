<?php

namespace App\Services\TimeSeries;

use App\Services\InfluxDBService;

class SensorTimeSeriesService
{
    public function __construct(private readonly InfluxDBService $influx)
    {
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
                $time = method_exists($rec, 'getTime') ? $rec->getTime() : ($rec->_time ?? ($rec['time'] ?? null));
                if ($time instanceof \DateTimeInterface) {
                    $time = $time->format(DATE_ATOM);
                }
                $value = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
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
            $minRes = $this->influx->queryPipeline($base . "\n|> min()");
            foreach ($minRes as $t) {
                foreach (($t->records ?? []) as $rec) {
                    $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                    if ($val !== null) { $stats['min'] = (float)$val; break 2; }
                }
            }
            // max
            $maxRes = $this->influx->queryPipeline($base . "\n|> max()");
            foreach ($maxRes as $t) {
                foreach (($t->records ?? []) as $rec) {
                    $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                    if ($val !== null) { $stats['max'] = (float)$val; break 2; }
                }
            }
            // mean
            $meanRes = $this->influx->queryPipeline($base . "\n|> mean()");
            foreach ($meanRes as $t) {
                foreach (($t->records ?? []) as $rec) {
                    $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                    if ($val !== null) { $stats['avg'] = (float)$val; break 2; }
                }
            }
            // count
            $countRes = $this->influx->queryPipeline($base . "\n|> count()");
            foreach ($countRes as $t) {
                foreach (($t->records ?? []) as $rec) {
                    $val = method_exists($rec, 'getValue') ? $rec->getValue() : ($rec->_value ?? ($rec['value'] ?? null));
                    if ($val !== null) { $stats['count'] = (int)$val; break 2; }
                }
            }
        } catch (\Throwable $e) {
            // ignore and return defaults
        }

        // Progressive widening if nothing found
        if (($stats['count'] ?? 0) === 0 && $range === '-24h') {
            return $this->stats($sensorId, '-7d');
        }
        if (($stats['count'] ?? 0) === 0 && $range === '-7d') {
            return $this->stats($sensorId, '-30d');
        }

        return $stats;
    }
}
