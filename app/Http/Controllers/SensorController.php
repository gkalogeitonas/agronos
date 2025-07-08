<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sensor;


class SensorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Scan or add a sensor by QR code (uuid). If the sensor exists, update it; otherwise, create it.
     */
    public function scan(Request $request)
    {
        $validated = $request->validate([
            'uuid'        => 'required|string',
            'device_uuid' => 'required|exists:devices,uuid',
            'lat'         => 'nullable|numeric',
            'lon'         => 'nullable|numeric',
            'type'        => 'nullable|string',
            'name'        => 'nullable|string',
        ]);

        // Find the device by its UUID
        $device = \App\Models\Device::where('uuid', $validated['device_uuid'])
                   ->firstOrFail();

        // Create or update the sensor
        $sensor = Sensor::updateOrCreate(
            ['uuid' => $validated['uuid']],
            [
                'device_id' => $device->id,
                'user_id'   => $request->user()->id,
                'lat'       => $validated['lat'] ?? null,
                'lon'       => $validated['lon'] ?? null,
                'type'      => $validated['type'] ?? null,
                'name'      => $validated['name'] ?? null,
            ]
        );

        // Prepare response message and status code
        $created = $sensor->wasRecentlyCreated;
        $message = $created ? 'Sensor created successfully' : 'Sensor updated successfully';
        $status = $created ? 201 : 200;

        return response()->json([
            'message' => $message,
            'sensor'  => $sensor,
        ], $status);
    }
}
