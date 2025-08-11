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
        // // Log the incoming device data
        // Log::info('Device data received', [
        //     'device_id' => optional($request->user())->id,
        //     'payload' => $request->all(),
        //     'ip' => $request->ip(),
        // ]);

        // Write each sensor measurement separately
        foreach ($request->validated()['sensors'] as $sensor) {
            $sensorModel = \App\Models\Sensor::where('uuid', $sensor['uuid'])->first();
            if (!$sensorModel) {
                // Log missing sensor and skip
                Log::warning('Sensor not found for uuid', ['uuid' => $sensor['uuid']]);
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
        }

        return response()->json(['message' => 'Data received'], 200);
    }
}
