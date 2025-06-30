<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use Illuminate\Support\Facades\Hash;

class DeviceAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'uuid' => 'required|string',
            'secret' => 'required|string',
        ]);

        $device = Device::where('uuid', $request->uuid)->first();

        if (! $device || ! Hash::check($request->secret, $device->secret)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $device->createToken('device-token')->plainTextToken;

        return response()->json(['token' => $token]);
    }
}
