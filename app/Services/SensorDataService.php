<?php

namespace App\Services;

use App\Events\SensorReadingEvent;
use App\Jobs\ProcessSensorInfluxData;
use App\Models\Sensor;

class SensorDataService
{
    public function processSensorData($device, array $sensorPayloads): array
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
            if (! $sensorModel) {
                $missingUuids[] = $sensor['uuid'];

                continue;
            }

            // Dispatch job to write to InfluxDB asynchronously
            ProcessSensorInfluxData::dispatch($sensorModel, (float) $sensor['value'], time());

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
