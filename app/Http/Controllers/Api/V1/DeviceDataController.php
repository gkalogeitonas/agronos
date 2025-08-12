<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Api\V1\DeviceDataRequest;
use App\Services\InfluxDBService;

class DeviceDataController extends Controller
{
    public function store(DeviceDataRequest $request, InfluxDBService $influx)
    {
        $missingUuids = [];
        $writtenCount = 0;
        $device = $request->user();
        foreach ($request->validated()['sensors'] as $sensor) {
            $sensorModel = \App\Models\Sensor::allTenants()->where('uuid', $sensor['uuid'])->first();
            if (!$sensorModel || $sensorModel->device_id !== $device->id) {
                $missingUuids[] = $sensor['uuid'];
                continue;
            }
            $influx->writeArray([
                'name' => 'sensor_measurement',
                'tags' => [
                    'user_id'    => $sensorModel->user_id,
                    'farm_id'    => $sensorModel->farm_id,
                    'sensor_id'  => $sensorModel->id,
                    'sensor_type'=> $sensorModel->type,
                ],
                'fields' => [
                    'value' => $sensor['value'],
                ],
                'time' => microtime(true), // use server time
            ]);
            $writtenCount++;
        }

        $response = ['message' => 'Data received.'];
        if (count($missingUuids) > 0) {
            $response['missing_uuids'] = $missingUuids;
        }
        return response()->json($response, 200);
    }
}
