<?php

namespace App\Services;

use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;

class InfluxDBService
{
    protected $client;
    protected $bucket;
    protected $org;

    public function __construct()
    {
        $this->client = new Client([
            'url' => config('services.influxdb.url'),
            'token' => config('services.influxdb.token'),
        ]);
        $this->bucket = config('services.influxdb.bucket');
        $this->org = config('services.influxdb.org');
    }

    /**
     * Expose bucket for consumers that might need it (read-only)
     */
    public function bucket(): string
    {
        return (string) $this->bucket;
    }

    /**
     * Expose org for consumers that might need it (read-only)
     */
    public function org(): string
    {
        return (string) $this->org;
    }

    /**
     * Helper to run a Flux pipeline by automatically prefixing the bucket.
     * Provide only the pipeline starting from `|> range(...)`.
     *
     * Example:
     *   queryPipeline("|> range(start: -1h) |> filter(fn: (r) => r._measurement == \"m\")")
     */
    public function queryPipeline(string $pipeline)
    {
        $flux = 'from(bucket: "' . $this->bucket . '")' . "\n" . ltrim($pipeline);
        return $this->query($flux);
    }

    /**
     * Write data using Line Protocol string
     */
    public function writeLineProtocol(string $data, $precision = WritePrecision::S)
    {
        $writeApi = $this->client->createWriteApi();
        $writeApi->write($data, $precision, $this->bucket, $this->org);
    }

    /**
     * Write data using a Point object
     */
    public function writePoint(Point $point, $precision = WritePrecision::S)
    {
        $writeApi = $this->client->createWriteApi();
        $writeApi->write($point, $precision, $this->bucket, $this->org);
    }

    /**
     * Write data using an array structure
     */
    public function writeArray(array $data, $precision = WritePrecision::S)
    {
        $writeApi = $this->client->createWriteApi();
        $writeApi->write($data, $precision, $this->bucket, $this->org);
    }

    /**
     * Properly close the client connection
     */
    public function close()
    {
        $this->client->close();
    }



    // Query all results from the measurement after writing

    // $flux = <<<FLUX
    // from(bucket: "Agronos")
    // |> range(start: -1h)
    // |> filter(fn: (r) => r._measurement == "sensor_measurement")
    // FLUX;

    // $queryResults = $influx->query($flux);
    // echo '<pre>';
    //  print_r($queryResults); // Print the query results for debugging
    //  echo '</pre>';
    public function query(string $flux)
    {
        $queryApi = $this->client->createQueryApi();
        return $queryApi->query($flux, $this->org);
    }

    /**
     * Get recent readings for a sensor, flattened to [{time, value}]
     */
    public function recentSensorReadings(int $sensorId, string $range = '-7d', int $limit = 10): array
    {
        $pipeline = <<<FLUX
|> range(start: {$range})
|> filter(fn: (r) => r._measurement == "sensor_measurement" and r.sensor_id == "{$sensorId}")
|> sort(columns: ["_time"], desc: true)
|> limit(n: {$limit})
FLUX;
        try {
            $result = $this->queryPipeline($pipeline);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($result as $table) {
            $records = $table->records ?? [];
            foreach ($records as $rec) {
                // Try to extract time/value in a client-agnostic way
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
    public function sensorStats(int $sensorId, string $range = '-24h'): array
    {
        $pipeline = <<<FLUX
|> range(start: {$range})
|> filter(fn: (r) => r._measurement == "sensor_measurement" and r.sensor_id == "{$sensorId}")
|> keep(columns: ["_value"])
|> group()
|> reduce(
    identity: {min: 999999.0, max: -999999.0, sum: 0.0, count: 0.0},
    fn: (r, accumulator) => ({
      min: if r._value < accumulator.min then r._value else accumulator.min,
      max: if r._value > accumulator.max then r._value else accumulator.max,
      sum: accumulator.sum + r._value,
      count: accumulator.count + 1.0
    })
  )
FLUX;
        try {
            $result = $this->queryPipeline($pipeline);
        } catch (\Throwable $e) {
            return ['min' => null, 'max' => null, 'avg' => null, 'count' => 0];
        }
        $min = $max = $sum = $count = null;
        foreach ($result as $table) {
            $records = $table->records ?? [];
            foreach ($records as $rec) {
                $vals = method_exists($rec, 'getValues') ? $rec->getValues() : (array) $rec;
                $min = $vals['min'] ?? $min;
                $max = $vals['max'] ?? $max;
                $sum = $vals['sum'] ?? $sum;
                $count = $vals['count'] ?? $count;
            }
        }
        $avg = ($count && $sum !== null) ? (float)$sum / (float)$count : null;
        return [
            'min' => $min !== null ? (float)$min : null,
            'max' => $max !== null ? (float)$max : null,
            'avg' => $avg,
            'count' => $count !== null ? (int)round((float)$count) : 0,
        ];
    }
}
