<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use Illuminate\Support\Facades\Hash;
use App\Enums\DeviceStatus;
use App\Services\MqttCredentialService;

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

        // Update status to ONLINE on successful auth
        $device->update([
            'status' => DeviceStatus::ONLINE,
            'last_seen_at' => now(),
        ]);

        $token = $device->createToken('device-token')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    // Device can call this after authenticating with the token to get mqtt credentials
    public function provisionMqttCredentials(Request $request, MqttCredentialService $svc)
    {
        $device = $request->user();

        $creds = $svc->createCredentials($device);

        return response()->json($creds, 200);
    }
}
