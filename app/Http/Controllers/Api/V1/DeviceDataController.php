<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceDataRequest;
use App\Services\SensorDataService;
use Illuminate\Http\Request;
use App\Models\Device;

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


    public function mqttBrokerWebhook(Request $request, SensorDataService $sensorDataService)
    {
        info('MQTT Broker Webhook GET called');
        $device = $request['username'];
        $payload = $request['payload'];
        // Find device by uuid (assuming username is the device UUID)
        $device = Device::where('uuid', $request['username'])->first();
        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }
        // // Update device status and last seen
        $device->update([
            'status' => DeviceStatus::ONLINE,
            'last_seen_at' => now(),
        ]);
        $sensorPayloads = $payload ? json_decode($payload, true) : [];
        $sensorPayloads = $sensorPayloads['sensors'] ?? [];
        $response = $sensorDataService->processSensorData($device, $sensorPayloads);

        return response()->json(['message' => 'Webhook received'], 200);
    }
}
