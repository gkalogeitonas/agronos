<?php

namespace App\Services;

use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;

/**
 * Test double for InfluxDBService that records writes instead of
 * performing network calls. Useful for unit/feature tests.
 */
class InfluxDBFake extends InfluxDBService
{
    /** @var array<int, array{type:string,payload:mixed,precision:mixed,bucket:?string,org:?string}> */
    protected array $writes = [];

    public function __construct()
    {
        // Intentionally do not call parent::__construct() to avoid creating a real client
        $this->bucket = config('services.influxdb.bucket');
        $this->org = config('services.influxdb.org');
        $this->client = null; // no real client
        echo "Using InfluxDBFake for testing purposes.\n";
    }

    public function writeLineProtocol(string $data, $precision = WritePrecision::S)
    {
        $this->writes[] = [
            'type' => 'line',
            'payload' => $data,
            'precision' => $precision,
            'bucket' => $this->bucket,
            'org' => $this->org,
        ];
    }

    public function writePoint(Point $point, $precision = WritePrecision::S)
    {
        $this->writes[] = [
            'type' => 'point',
            'payload' => $point,
            'precision' => $precision,
            'bucket' => $this->bucket,
            'org' => $this->org,
        ];
    }

    public function writeArray(array $data, $precision = WritePrecision::S)
    {
        $this->writes[] = [
            'type' => 'array',
            'payload' => $data,
            'precision' => $precision,
            'bucket' => $this->bucket,
            'org' => $this->org,
        ];
    }

    public function close()
    {
        // no-op
    }

    /**
     * Return all recorded writes for assertions.
     */
    public function writes(): array
    {
        return $this->writes;
    }

    /**
     * Clear recorded writes.
     */
    public function reset(): void
    {
        $this->writes = [];
    }
}
