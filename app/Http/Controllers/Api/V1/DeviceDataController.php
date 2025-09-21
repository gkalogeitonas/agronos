<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceDataRequest;
use App\Services\SensorDataService;

class DeviceDataController extends Controller
{
    public function store(DeviceDataRequest $request, SensorDataService $sensorDataService)
    {
        $device = $request->user();
        // Update device status and last seen
        $device->update([
            'status' => DeviceStatus::ONLINE,
            'last_seen_at' => now(),
        ]);
        $sensorPayloads = $request->validated()['sensors'];
        $response = $sensorDataService->processSensorData($device, $sensorPayloads);

        return response()->json($response, 200);
    }
}
