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
}
