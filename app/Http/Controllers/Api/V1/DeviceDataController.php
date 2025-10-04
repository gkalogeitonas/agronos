<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceDataRequest;
use App\Services\SensorDataService;
use Illuminate\Http\Request;

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


    public function mqttBrokerWebhook(Request $request)
    {
        info('MQTT Broker Webhook GET called');
        info($request->all());
        // // This endpoint is called by the MQTT broker webhook integration
        // // The request is validated by DeviceDataRequest to ensure required fields are present
        // $device = $request->user();
        // // Update device status and last seen
        // $device->update([
        //     'status' => DeviceStatus::ONLINE,
        //     'last_seen_at' => now(),
        // ]);
        // $sensorPayloads = $request->validated()['sensors'];
        // $response = $sensorDataService->processSensorData($device, $sensorPayloads);

        return response()->json(['message' => 'Webhook received'], 200);
    }
}
