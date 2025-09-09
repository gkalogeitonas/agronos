<?php

use App\Models\Device;
use App\Models\Sensor;
use App\Services\InfluxDBFake;
use App\Services\SensorDataService;
use App\Events\SensorReadingEvent;
use Illuminate\Support\Facades\Event;


test('processing sensor data updates model and broadcasts reading', function () {
    Event::fake();

    // Create a device and a sensor for that device
    $device = \App\Models\Device::factory()->create();
    $sensor = Sensor::factory()->create([
        'device_id' => $device->id,
        'uuid' => 'sensor-uuid-123',
        'last_reading' => null,
        'last_reading_at' => null,
    ]);

    // Prepare a fake influx client
    $influx = new InfluxDBFake();

    $service = new SensorDataService();

    $value = 42.5;
    $payloads = [
        ['uuid' => $sensor->uuid, 'value' => $value],
    ];

    $response = $service->processSensorData($device, $payloads, $influx);

    // Sensor model should be updated
    $sensor->refresh();
    expect($sensor->last_reading)->toBe($value);
    expect($sensor->last_reading_at)->not->toBeNull();

    // Event dispatched with correct payload
    Event::assertDispatched(SensorReadingEvent::class, function ($event) use ($sensor, $value) {
        return $event->sensorId === $sensor->id
            && isset($event->payload['value'])
            && $event->payload['value'] == $value
            && isset($event->payload['time']);
    });
});
