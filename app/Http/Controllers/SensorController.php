<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Farm;
use App\Services\InfluxDBService;

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
        $farms = request()->user()->farms;
        return Inertia::render('Sensors/Index', [
            'sensors' => $sensors,
            'farms' => $farms,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(?Farm $farm = null)
    {
        $this->authorize('create', Sensor::class);
        $farms = request()->user()->farms;
        return Inertia::render('Sensors/Create', [
            'farms' => $farms,
            'selectedFarm' => $farm,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Sensor::class);
        $validated = $request->validate([
            'device_uuid' => 'required|exists:devices,uuid',
            'uuid' => 'required|string|unique:sensors,uuid',
            'farm_id' => 'nullable|exists:farms,id',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
            'type' => 'nullable|string',
            'name' => 'nullable|string'
        ]);


        $device = \App\Models\Device::where('uuid', $validated['device_uuid'])
                   ->firstOrFail();
        $this->authorize('view', $device); // This uses DevicePolicy

        $sensor = Sensor::create(
            collect($validated)
                ->except('device_uuid')
                ->toArray()
            + [
                'user_id' => $request->user()->id,
                'device_id' => $device->id,
            ]
        );
        return redirect()->route('sensors.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Sensor $sensor)
    {
        $this->authorize('view', $sensor);
        $sensor->load(['farm', 'device']);

        // InfluxDB queries via service helpers
        $influx = app(InfluxDBService::class);
        $recent = $influx->recentSensorReadings($sensor->id, '-7d', 10);
        $statsArr = $influx->sensorStats($sensor->id, '-24h');

        return Inertia::render('Sensors/Show', [
            'sensor' => $sensor,
            'recentReadings' => $recent,
            'stats' => $statsArr,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sensor $sensor)
    {
        $this->authorize('update', $sensor);
        $sensor->load(['farm', 'device']);
        $farms = request()->user()->farms;
        return Inertia::render('Sensors/Edit', [
            'sensor' => $sensor,
            'farms' => $farms,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sensor $sensor)
    {
        $this->authorize('update', $sensor);
        $validated = $request->validate([
            'name' => 'nullable|string',
            'farm_id' => 'nullable|exists:farms,id',
            'lat' => 'nullable|numeric',
            'lon' => 'nullable|numeric',
        ]);

        $sensor->update($validated);

        return redirect()->route('sensors.show', $sensor->id);
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
        $validated = $request->validate([
            'uuid'        => 'required|string',
            'device_uuid' => 'required|exists:devices,uuid',
            'lat'         => 'nullable|numeric',
            'lon'         => 'nullable|numeric',
            'type'        => 'nullable|string',
            'name'        => 'nullable|string',
        ]);

        $device = \App\Models\Device::where('uuid', $validated['device_uuid'])->firstOrFail();
        $sensor = Sensor::where('uuid', $validated['uuid'])->first();
        $this->authorize('view', $device); // This uses DevicePolicy

        if ($sensor) {
            // Update sensor, but never update device_id or user_id from scan
            $sensor->update(collect($validated)->except(['device_uuid', 'uuid'])->toArray());
            return redirect()->route('sensors.index');
        } else {
            // Create sensor, pass device_id and user_id
            return $this->store($request);
        }
    }
}
