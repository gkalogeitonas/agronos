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
        // Log the incoming device data
        Log::info('Device data received', [
            'device_id' => optional($request->user())->id,
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);


        $influx->writeArray([
            'name' => 'sensor_measurement',
            'tags' => [
                'user_id'    => optional($request->user())->id,
                'farm_id'    => $request->input('farm_id'),
                'sensor_id'  => $request->input('sensor_id'),
                'sensor_type'=> $request->input('sensor_type'),
            ],
            'fields' => $request->validated(), // sensor readings
            'time'   => microtime(true), // use server time
        ]);

        return response()->json(['message' => 'Data received'], 200);
    }
}
