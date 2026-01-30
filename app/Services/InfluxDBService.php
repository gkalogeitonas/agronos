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
        $flux = 'from(bucket: "'.$this->bucket.'")'."\n".ltrim($pipeline);

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

    /**
     * Run a Flux query and return the result.
     * Use this for queries that return data.
     */
    // Note: Flux queries should be properly formatted with `from(bucket: ...)` included.
    // If you need to prepend the bucket, use queryPipeline() instead.
    // This method is for direct queries that already include the bucket.
    public function query(string $flux)
    {
        $queryApi = $this->client->createQueryApi();

        return $queryApi->query($flux, $this->org);
    }
}
