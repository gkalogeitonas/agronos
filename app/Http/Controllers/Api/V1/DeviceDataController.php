<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Api\V1\DeviceDataRequest;
use App\Services\InfluxDBService;
use App\Services\SensorMeasurementPayloadFactory;

class DeviceDataController extends Controller
{
    public function store(DeviceDataRequest $request, InfluxDBService $influx)
    {
        $device = $request->user();
        $sensorPayloads = $request->validated()['sensors'];

        // Fetch all sensors for these UUIDs in a single query
        $sensors = \App\Models\Sensor::allTenants()
            ->where('device_id', $device->id)
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
            // Update last_reading and last_reading_at
            $sensorModel->last_reading = $sensor['value'];
            $sensorModel->last_reading_at = now();
            $sensorModel->save();
            $writtenCount++;
        }

        $response = ['message' => 'Data received.'];
        if (count($missingUuids) > 0) {
            $response['missing_uuids'] = $missingUuids;
        }
        return response()->json($response, 200);
    }
}
