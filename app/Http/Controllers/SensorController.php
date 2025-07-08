<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SensorController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Sensor::class);
        $sensors = request()->user()->sensors;
        return Inertia::render('Sensors/Index', ['sensors' => $sensors]);
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
        $this->authorize('create', Sensor::class);
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'uuid' => 'required|string|unique:sensors,uuid',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
            'type' => 'nullable|string',
            'name' => 'nullable|string'
        ]);

        $sensor = Sensor::create($validated + ['user_id' => $request->user()->id]);

        return redirect()->route('sensors.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Sensor $sensor)
    {
        $this->authorize('view', $sensor);
        return Inertia::render('Sensors/Show', ['sensor' => $sensor]);
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
    public function update(Request $request, Sensor $sensor)
    {
        $this->authorize('update', $sensor);
        $validated = $request->validate([
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
            'type' => 'nullable|string',
            'name' => 'nullable|string'
        ]);

        $sensor->update($validated);

        return redirect()->route('sensors.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sensor $sensor)
    {
        $this->authorize('delete', $sensor);
        $sensor->delete();
        return redirect()->route('sensors.index');
    }

    /**
     * Scan or add a sensor by QR code (uuid). If the sensor exists, update it; otherwise, create it.
     */
    public function scan(Request $request)
    {
        $this->authorize('create', Sensor::class);
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
