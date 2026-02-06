<?php

namespace App\Http\Controllers;

use App\Enums\SensorType;
use App\Http\Requests\SensorRequest\ScanSensorRequest;
use App\Http\Requests\SensorRequest\StoreSensorRequest;
use App\Http\Requests\SensorRequest\UpdateSensorRequest;
use App\Http\Resources\SensorResource;
use App\Models\Farm;
use App\Models\Sensor;
use App\Services\TimeSeries\SensorTimeSeriesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;

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
            'SensorTypes' => SensorType::values(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSensorRequest $request)
    {
        $this->authorize('create', Sensor::class);
        $validated = $request->validated();

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

        // Prepare time-series service; defer heavy/time-series work so initial page is fast
        $ts = app(SensorTimeSeriesService::class);

        return Inertia::render('Sensors/Show', [
            'sensor' => (new SensorResource($sensor))->flat(request()),
            // defer recent readings and stats (resolved asynchronously by Inertia)
            'recentReadings' => Inertia::defer(fn () => $ts->recentReadings($sensor->id, '-7d', 20)),
            'stats' => Inertia::defer(fn () => $ts->stats($sensor->id, '-24h')),
            'chartData' => Inertia::defer(fn () => $ts->chartReadings($sensor->id, '-7d')), // Για το γράφημα
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
    public function update(UpdateSensorRequest $request, Sensor $sensor)
    {
        $this->authorize('update', $sensor);
        $validated = $request->validated();

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
    public function scan(ScanSensorRequest $request)
    {
        $validated = $request->validated();

        $device = \App\Models\Device::where('uuid', $validated['device_uuid'])->firstOrFail();
        $sensor = Sensor::where('uuid', $validated['uuid'])->first();
        $this->authorize('view', $device); // This uses DevicePolicy

        if ($sensor) {
            // Update sensor, but never update device_id or user_id from scan
            $sensor->update(collect($validated)->except(['device_uuid', 'uuid'])->toArray());

            return redirect()->route('sensors.index');
        } else {
            // Create sensor, ensure user can create
            $this->authorize('create', Sensor::class);

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
    }
}
