<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Api\V1\DeviceDataRequest;

class DeviceDataController extends Controller
{
    public function store(DeviceDataRequest $request)
    {
        // Log the incoming device data
        Log::info('Device data received', [
            'device_id' => optional($request->user())->id,
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Data received'], 200);
    }
}
