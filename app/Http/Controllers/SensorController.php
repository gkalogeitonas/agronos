<?php

namespace App\Http\Controllers;

use App\Enums\SensorType;
use App\Enums\TimeRange;
use App\Http\Requests\SensorRequest\ScanSensorRequest;
use App\Http\Requests\SensorRequest\SensorRequest;
use App\Http\Requests\SensorRequest\StoreSensorRequest;
use App\Http\Requests\SensorRequest\UpdateSensorRequest;
use App\Http\Resources\SensorResource;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Scopes\TenantScope;
use App\Services\TimeSeries\SensorTimeSeriesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SensorController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Sensor::class);
        $sensors = request()->user()->sensors()->with('device:id,name,status,farm_id')->orderBy('name')->get();
        $farms = request()->user()->farms()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Sensors/Index', [
            'sensors' => $sensors,
            'farms' => $farms,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, ?Farm $farm = null): Response
    {
        $this->authorize('create', Sensor::class);

        $devices = $request->user()->devices()
            ->with(['farm:id,name,coordinates', 'farm.zones' => fn ($zones) => $zones->orderBy('name')->select(['id', 'farm_id', 'name'])])
            ->orderBy('name')
            ->get(['id', 'name', 'uuid', 'farm_id']);

        return Inertia::render('Sensors/Create', [
            'devices' => $devices->map(fn (Device $device): array => [
                ...$device->only(['id', 'name', 'uuid']),
                'farm' => $device->farm === null ? null : [
                    ...$device->farm->only(['id', 'name', 'coordinates']),
                    'zones' => $device->farm->zones->map->only(['id', 'name'])->values(),
                ],
            ]),
            'selectedFarm' => $farm?->only(['id', 'name', 'coordinates']),
            'prefillDeviceUuid' => $devices->firstWhere('id', $request->integer('device'))?->uuid,
            'SensorTypes' => SensorType::values(),
            'unregisteredDeviceMessage' => SensorRequest::UNREGISTERED_DEVICE_MESSAGE,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSensorRequest $request)
    {
        $this->authorize('create', Sensor::class);
        $validated = $request->validated();

        $device = $this->findRegisteredDevice($validated['device_uuid']);
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

        return redirect()->route('sensors.index')->with('success', 'Sensor added.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Sensor $sensor)
    {
        $this->authorize('view', $sensor);
        $sensor->load('device.farm', 'zone:id,name');

        // Prepare time-series service; defer heavy/time-series work so initial page is fast
        $ts = app(SensorTimeSeriesService::class);

        // Respect optional ?range= query param; fall back to default
        $range = request()->query('range', TimeRange::default()->value);

        return Inertia::render('Sensors/Show', [
            'sensor' => (new SensorResource($sensor))->flat(request()),
            // expose available ranges and which is selected
            'rangeOptions' => TimeRange::options(),
            'selectedRange' => $range,
            // defer recent readings and stats (resolved asynchronously by Inertia)
            'recentReadings' => Inertia::defer(fn () => $ts->recentReadings($sensor->id, $range, 20)),
            'stats' => Inertia::defer(fn () => $ts->stats($sensor->id, $range)),
            'chartData' => Inertia::defer(fn () => $ts->chartReadings($sensor->id, $range)), // For chart, we want all readings in range
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sensor $sensor): Response
    {
        $this->authorize('update', $sensor);
        $sensor->load('device:id,uuid,farm_id', 'device.farm:id,name,coordinates');

        return Inertia::render('Sensors/Edit', [
            'sensor' => $sensor,
            'zones' => $sensor->device?->farm?->zones()->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->values() ?? [],
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

        return redirect()->route('sensors.show', $sensor->id)->with('success', 'Sensor updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sensor $sensor)
    {
        $this->authorize('delete', $sensor);
        $sensor->delete();

        return redirect()->route('sensors.index')->with('success', 'Sensor deleted.');
    }

    /**
     * Scan or add a sensor by QR code (uuid). If the sensor exists, update it; otherwise, create it.
     *
     * A repeated QR scan updates the existing sensor directly, but never its zone (ADR-0009). A UUID typed by hand
     * (`manual_entry`) that matches an existing sensor is rejected until the user
     * confirms the update (`confirm_update`), so a typo cannot overwrite a sensor.
     *
     * @throws ValidationException
     */
    public function scan(ScanSensorRequest $request)
    {
        $validated = $request->validated();
        $sensorAttributes = collect($validated)->except(['manual_entry', 'confirm_update']);

        $device = $this->findRegisteredDevice($validated['device_uuid']);
        $sensor = Sensor::where('uuid', $validated['uuid'])->first();
        $this->authorize('view', $device); // This uses DevicePolicy

        if ($sensor) {
            if ($request->boolean('manual_entry') && ! $request->boolean('confirm_update')) {
                $existingSensor = $sensor->name ? "sensor \"{$sensor->name}\"" : 'sensor';

                throw ValidationException::withMessages([
                    'confirm_update' => "This UUID belongs to an existing {$existingSensor}. Confirm to update it, or check the UUID.",
                ]);
            }

            // Update sensor, but never update device_id or user_id from scan
            $sensor->update($sensorAttributes->except(['device_uuid', 'uuid', 'zone_id'])->toArray());

            return redirect()->route('sensors.index')->with('success', 'Sensor updated.');
        }

        // Create sensor, ensure user can create
        $this->authorize('create', Sensor::class);

        if (Sensor::withoutGlobalScope(TenantScope::class)->where('uuid', $validated['uuid'])->exists()) {
            throw ValidationException::withMessages([
                'uuid' => 'This sensor UUID cannot be used. Check the UUID.',
            ]);
        }

        Sensor::create(
            $sensorAttributes->except('device_uuid')->toArray()
            + [
                'user_id' => $request->user()->id,
                'device_id' => $device->id,
            ]
        );

        return redirect()->route('sensors.index')->with('success', 'Sensor added.');
    }

    /**
     * Find the device under the tenant scope. A device owned by another tenant gets the same
     * error as a missing one, so its existence is not revealed.
     *
     * @throws ValidationException
     */
    private function findRegisteredDevice(string $uuid): Device
    {
        return Device::where('uuid', $uuid)->first()
            ?? throw ValidationException::withMessages([
                'device_uuid' => SensorRequest::UNREGISTERED_DEVICE_MESSAGE,
            ]);
    }
}
