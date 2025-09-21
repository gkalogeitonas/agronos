<?php

use App\Jobs\ProcessSensorInfluxData;
use App\Models\Sensor;
use App\Services\InfluxDBService;

test('job processes sensor data successfully', function () {
    $sensor = Sensor::factory()->create(['type' => 'temperature']);
    $value = 25.5;
    $timestamp = time();

    $job = new ProcessSensorInfluxData($sensor, $value, $timestamp);

    // Mock InfluxDB service
    $influxMock = Mockery::mock(InfluxDBService::class);
    $influxMock->shouldReceive('writeArray')
        ->once()
        ->with(Mockery::on(function ($payload) use ($sensor, $value, $timestamp) {
            return $payload['name'] === 'sensor_measurement'
                && $payload['tags']['sensor_id'] === $sensor->id
                && $payload['fields']['value'] === $value
                && $payload['time'] === $timestamp;
        }));

    $job->handle($influxMock);
});

test('job rethrows exception on failure', function () {
    $sensor = Sensor::factory()->create(['type' => 'temperature']);
    $value = 25.5;
    $timestamp = time();

    $job = new ProcessSensorInfluxData($sensor, $value, $timestamp);

    // Mock InfluxDB service to throw exception
    $influxMock = Mockery::mock(InfluxDBService::class);
    $influxMock->shouldReceive('writeArray')
        ->once()
        ->andThrow(new \Exception('InfluxDB connection failed'));

    expect(fn() => $job->handle($influxMock))->toThrow(\Exception::class);
});

test('job has correct configuration', function () {
    $sensor = Sensor::factory()->create(['type' => 'temperature']);
    $job = new ProcessSensorInfluxData($sensor, 25.5, time());

    expect($job->tries)->toBe(3);
    expect($job->timeout)->toBe(30);
    expect($job->queue)->toBe('sensor-data');
});
