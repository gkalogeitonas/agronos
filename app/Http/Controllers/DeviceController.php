<?php

namespace App\Http\Controllers;

use App\Enums\DeviceType;
use App\Enums\SensorType;
use App\Http\Requests\RegisterDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceDetailResource;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\Farm;
use App\Services\MqttCredentialService;
use App\Services\TimeSeries\SensorTimeSeriesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    use AuthorizesRequests;

    private const DEVICES_PER_PAGE = 25;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Device::class);

        $filters = [
            'status' => $request->string('status', 'all')->value(),
            'farm' => $request->string('farm', 'all')->value(),
            'link' => $request->string('link', 'all')->value(),
            'search' => $request->string('search')->trim()->value(),
        ];

        $devices = Device::query()
            ->with('farm:id,name')
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['farm'] !== 'all', fn ($query) => $query->onFarmFilter($filters['farm']))
            ->when($filters['link'] !== 'all', fn ($query) => $query->where('type', $filters['link']))
            ->when($filters['search'] !== '', fn ($query) => $query->whereAny(['name', 'uuid'], 'like', '%'.$filters['search'].'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::DEVICES_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Devices/Index', [
            'devices' => DeviceResource::collection($devices->items())->resolve(),
            'pagination' => $this->paginationMeta($devices),
            'filters' => $filters,
            ...$this->deviceFilterOptions(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $farms = $this->farmOptions();

        return Inertia::render('Devices/Create', [
            'prefillUuid' => $request->query('uuid'),
            'prefillFarmId' => $farms->firstWhere('id', $request->integer('farm'))['id'] ?? null,
            'farms' => $farms,
            'deviceTypes' => collect(DeviceType::cases())->map(fn ($type) => [
                'label' => DeviceType::labels()[$type->value],
                'value' => $type->value,
            ])->values(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RegisterDeviceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $device = Device::create([
            'user_id' => $request->user()->id,
            'uuid' => $validated['uuid'],
            'secret' => Hash::make($validated['secret']), // Hashing here is correct
            'name' => $validated['name'] ?? null,
            'type' => $validated['type'],
            'status' => 'registered',
            'lora_aes_key' => $validated['lora_aes_key'] ?? null,
            'farm_id' => $validated['farm_id'] ?? null,
        ]);

        return redirect()->route('devices.show', $device)->with('success', 'Device registered successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Device $device): Response
    {
        $this->authorize('view', $device);

        $sensors = $device->load('sensors')->sensors;

        $ts = app(SensorTimeSeriesService::class);

        // Prepare deferred battery time-series (only if a battery sensor exists)
        $batterySensorId = optional($sensors->firstWhere('type', SensorType::BATTERY->value))->id;

        return Inertia::render('Devices/Show', [
            'device' => (new DeviceDetailResource($device->withoutRelations()->load('farm:id,name')))->resolve(),
            'sensors' => $sensors,
            'batteryTimeSeries' => Inertia::defer(fn () => $batterySensorId ? $ts->recentReadings((int) $batterySensorId, '-24h', 100) : []),
        ]);
    }

    /**
     * Ensure MQTT credentials exist for the given device and return them via flash.
     */
    public function createMqttCredentials(Device $device)
    {
        $this->authorize('update', $device);

        $service = app(MqttCredentialService::class);
        $result = $service->createCredentials($device);

        return redirect()->route('devices.show', $device)->with('mqtt_credentials', $result);
    }

    /**
     * Show the form for editing the device's name and farm.
     */
    public function edit(Device $device): Response
    {
        $this->authorize('update', $device);

        return Inertia::render('Devices/Edit', [
            'device' => $device->only(['id', 'name', 'uuid', 'type', 'farm_id']),
            'farms' => $this->farmOptions(),
        ]);
    }

    /**
     * Update the device's name and farm. Its sensors keep their positions, but leave
     * their zones when the device changes farm, because a zone belongs to one farm.
     */
    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        DB::transaction(function () use ($request, $device): void {
            $device->fill($request->validated());

            if ($device->isDirty('farm_id')) {
                $device->sensors()->update(['zone_id' => null]);
            }

            $device->save();
        });

        return redirect()->route('devices.show', $device)->with('success', 'Device updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Device $device)
    {
        $this->authorize('delete', $device);
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Device deleted.');
    }

    /**
     * Filter choices and status counts across all the tenant's devices, whatever page or filter is shown.
     *
     * @return array{statusCounts: array<string, int>, farms: Collection<int, array{id: int, name: string}>, hasUnassigned: bool, linkTypes: list<string>}
     */
    private function deviceFilterOptions(): array
    {
        $statusCounts = Device::query()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total);

        return [
            'statusCounts' => ['all' => $statusCounts->sum(), ...$statusCounts->all()],
            'farms' => $this->farmOptions(),
            'hasUnassigned' => Device::query()->whereNull('farm_id')->exists(),
            'linkTypes' => Device::query()->toBase()->distinct()->orderBy('type')->pluck('type')->all(),
        ];
    }

    /**
     * The tenant's farms a device can be placed on.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function farmOptions(): Collection
    {
        return Farm::query()->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name']);
    }
}
