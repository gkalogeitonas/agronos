<?php

namespace App\Services;

use App\Models\Sensor;
use App\Services\InfluxDBService;
use App\Services\SensorMeasurementPayloadFactory;
use Illuminate\Support\Collection;
use App\Events\SensorReadingEvent;

class SensorDataService
{
    public function processSensorData($device, array $sensorPayloads, InfluxDBService $influx): array
    {
        $uuids = collect($sensorPayloads)->pluck('uuid')->all();
        $sensors = Sensor::allTenants()
            ->where('device_id', $device->id)
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');
        $missingUuids = [];
        $writtenCount = 0;

        foreach ($sensorPayloads as $sensor) {
            $sensorModel = $sensors->get($sensor['uuid']);
            if (!$sensorModel) {
                $missingUuids[] = $sensor['uuid'];
                continue;
            }
            $payload = SensorMeasurementPayloadFactory::make($sensorModel, $sensor['value']);
            $influx->writeArray($payload);
            $sensorModel->last_reading = $sensor['value'];
            $sensorModel->last_reading_at = now();
            $sensorModel->save();
            // Broadcast the new reading to any listeners (private per-sensor channel)
            event(new SensorReadingEvent(
                $sensorModel->id,
                [
                    'value' => $sensorModel->last_reading,
                    'time' => optional($sensorModel->last_reading_at)->toDateTimeString() ?: (string) $sensorModel->last_reading_at ?: now()->toDateTimeString(),
                ]
            ));
            $writtenCount++;
        }

        $response = ['message' => 'Data received.'];
        if (count($missingUuids) > 0) {
            $response['missing_uuids'] = $missingUuids;
        }
        return $response;
    }
}
