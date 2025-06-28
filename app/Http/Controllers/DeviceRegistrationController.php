<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterDeviceRequest;

class DeviceRegistrationController extends Controller
{
    public function registerByUser(RegisterDeviceRequest $request)
    {
        // Validate the request data
        $validated = $request->validated();

        $device = Device::create([
            'user_id' => $request->user()->id,
            'uuid' => $validated['uuid'],
            'secret' => $validated['secret'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'status' => 'registered',
        ]);

        return response()->json([
            'message' => 'Device registered successfully',
            'device' => $device,
        ], 201);
    }
}
