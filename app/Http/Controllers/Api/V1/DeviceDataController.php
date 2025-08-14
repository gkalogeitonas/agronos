<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceDataRequest;
use App\Services\InfluxDBService;
use App\Services\SensorDataService;
use App\Enums\DeviceStatus;

class DeviceDataController extends Controller
{
    public function store(DeviceDataRequest $request, InfluxDBService $influx, SensorDataService $sensorDataService)
    {
        $device = $request->user();
        // Update device status and last seen
        $device->update([
            'status' => DeviceStatus::ONLINE,
            'last_seen_at' => now(),
        ]);
        $sensorPayloads = $request->validated()['sensors'];
        $response = $sensorDataService->processSensorData($device, $sensorPayloads, $influx);
        return response()->json($response, 200);
    }
}
