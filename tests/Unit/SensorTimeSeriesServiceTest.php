<?php

use App\Services\InfluxDBService;
use App\Services\TimeSeries\SensorTimeSeriesService;
use Carbon\Carbon;

it('returns aggregated chart series formatted for ApexCharts', function () {
    // Prepare fake InfluxDB response
    $record1 = (object) ['_time' => '2026-02-06T12:00:00Z', '_value' => 12.3456];
    $record2 = (object) ['_time' => '2026-02-06T13:00:00Z', '_value' => 13.789];
    $table = (object) ['records' => [$record1, $record2]];

    $influx = Mockery::mock(InfluxDBService::class);
    $influx->shouldReceive('queryPipeline')->once()->andReturn([$table]);

    $svc = new SensorTimeSeriesService($influx);

    $out = $svc->chartReadings(5, '-7d');

    expect($out)->toBeArray();
    expect(count($out))->toBe(2);

    $expectedMs1 = (int) (Carbon::parse($record1->_time)->timestamp * 1000);
    $expectedMs2 = (int) (Carbon::parse($record2->_time)->timestamp * 1000);

    expect($out[0][0])->toBe($expectedMs1);
    expect($out[0][1])->toBe(12.35);
    expect($out[1][0])->toBe($expectedMs2);
    expect($out[1][1])->toBe(13.79);
});
