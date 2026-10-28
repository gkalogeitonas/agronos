<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Sensor;
use App\Services\SensorDataService;
use Database\Seeders\DevEnvironmentSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

#[Signature('devices:simulate
    {devices?* : UUIDs of the devices to simulate (defaults to every device with sensors)}
    {--via=direct : How readings are sent: "direct" (SensorDataService) or "http" (device API)}
    {--secret='.DevEnvironmentSeeder::DEVICE_SECRET.' : Plaintext device secret used to log in with --via=http}
    {--url= : Base URL of the device API (defaults to APP_URL)}
    {--interval=10 : Seconds between readings}
    {--once : Send a single round of readings and exit}')]
#[Description('Send fake sensor readings for virtual devices in local development')]
class SimulateDevices extends Command
{
    private const TRANSPORTS = ['direct', 'http'];

    /**
     * Value range and maximum change per reading for each sensor type.
     *
     * @var array<string, array{min: float, max: float, step: float}>
     */
    private const RANGES = [
        'temperature' => ['min' => 5, 'max' => 40, 'step' => 0.5],
        'humidity' => ['min' => 20, 'max' => 100, 'step' => 2],
        'moisture' => ['min' => 0, 'max' => 100, 'step' => 2],
        'light' => ['min' => 0, 'max' => 100000, 'step' => 2000],
        'battery' => ['min' => 0, 'max' => 100, 'step' => 1],
        'other' => ['min' => 0, 'max' => 100, 'step' => 5],
    ];

    /**
     * Last value sent per sensor id, so each reading drifts from the previous one.
     *
     * @var array<int, float>
     */
    private array $lastValues = [];

    /**
     * Sanctum token per device id for the http transport.
     *
     * @var array<int, string>
     */
    private array $tokens = [];

    /**
     * Execute the console command.
     */
    public function handle(SensorDataService $sensorDataService): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Device simulation is disabled in production.');

            return self::FAILURE;
        }

        $via = $this->option('via');

        if (! in_array($via, self::TRANSPORTS, true)) {
            $this->error('Unsupported --via value "'.$via.'". Use one of: '.implode(', ', self::TRANSPORTS).'.');

            return self::FAILURE;
        }

        $devices = $this->devices($via);

        if ($devices->isEmpty()) {
            $this->error('No devices with sensors found to simulate.');

            return self::FAILURE;
        }

        $interval = max(1, (int) $this->option('interval'));

        $this->info("Simulating {$devices->count()} device(s) via {$via}. Press Ctrl+C to stop.");

        while (true) {
            $devices = $devices->filter(fn (Device $device) => $via === 'http'
                ? $this->sendOverHttp($device)
                : $this->sendDirect($device, $sensorDataService));

            if ($devices->isEmpty()) {
                $this->error('No devices left to simulate.');

                return self::FAILURE;
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            Sleep::for($interval)->seconds();
        }
    }

    /**
     * @return Collection<int, Device>
     */
    private function devices(string $via): Collection
    {
        $uuids = $this->argument('devices');

        $devices = Device::allTenants()
            ->when($uuids, fn ($query) => $query->whereIn('uuid', $uuids))
            ->has('sensors')
            ->with('sensors')
            ->get();

        if ($via === 'direct') {
            return $devices;
        }

        return $devices->reject(function (Device $device) {
            if ($device->isLoRa()) {
                $this->warn("[{$device->uuid}] Skipped: LoRa devices send through the LoRa gateway, not the device API.");
            }

            return $device->isLoRa();
        });
    }

    private function sendDirect(Device $device, SensorDataService $sensorDataService): bool
    {
        $device->update([
            'status' => DeviceStatus::ONLINE,
            'last_seen_at' => now(),
        ]);

        $payloads = $this->readingsFor($device);
        $sensorDataService->processSensorData($device, $payloads);
        $this->reportReadings($device, $payloads);

        return true;
    }

    /**
     * Post a round of readings through the device API, logging in first if needed.
     * Returns false when the device cannot authenticate, so it is dropped from the simulation.
     */
    private function sendOverHttp(Device $device): bool
    {
        $payloads = $this->readingsFor($device);
        $response = $this->postReadings($device, $payloads);

        if ($response?->unauthorized()) {
            unset($this->tokens[$device->id]);
            $response = $this->postReadings($device, $payloads);
        }

        if ($response === null) {
            return false;
        }

        if ($response->failed()) {
            $this->warn("[{$device->uuid}] Data request failed with HTTP {$response->status()}: {$response->body()}");

            return true;
        }

        $this->reportReadings($device, $payloads);

        if ($missing = $response->json('missing_uuids')) {
            $this->warn("[{$device->uuid}] Unknown sensor UUIDs: ".implode(', ', $missing));
        }

        return true;
    }

    /**
     * @param  list<array{uuid: string, value: float}>  $payloads
     */
    private function postReadings(Device $device, array $payloads): ?Response
    {
        $token = $this->tokens[$device->id] ??= $this->login($device);

        if ($token === null) {
            unset($this->tokens[$device->id]);

            return null;
        }

        return $this->api()->withToken($token)->post('api/v1/device/data', ['sensors' => $payloads]);
    }

    private function login(Device $device): ?string
    {
        $response = $this->api()->post('api/v1/device/login', [
            'uuid' => $device->uuid,
            'secret' => $this->option('secret'),
        ]);

        if ($response->failed() || ! $response->json('token')) {
            $this->warn("[{$device->uuid}] Skipped: login failed with HTTP {$response->status()}. Check --secret.");

            return null;
        }

        return $response->json('token');
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl($this->option('url') ?: config('app.url'))->acceptJson();
    }

    /**
     * @return list<array{uuid: string, value: float}>
     */
    private function readingsFor(Device $device): array
    {
        return $device->sensors
            ->map(fn (Sensor $sensor) => ['uuid' => $sensor->uuid, 'value' => $this->nextValue($sensor)])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{uuid: string, value: float}>  $payloads
     */
    private function reportReadings(Device $device, array $payloads): void
    {
        $readings = collect($payloads)->map(fn (array $payload) => "{$payload['uuid']}={$payload['value']}")->implode(', ');
        $this->line("[{$device->uuid}] {$readings}");
    }

    /**
     * Drift the sensor's previous value by a small random step, staying within its type's range.
     */
    private function nextValue(Sensor $sensor): float
    {
        $type = SensorType::tryFrom((string) $sensor->type)?->value ?? SensorType::OTHER->value;
        ['min' => $min, 'max' => $max, 'step' => $step] = self::RANGES[$type];

        $previous = $this->lastValues[$sensor->id] ?? (float) ($sensor->last_reading ?? ($min + $max) / 2);
        $delta = (mt_rand() / mt_getrandmax() * 2 - 1) * $step;

        return $this->lastValues[$sensor->id] = round(min($max, max($min, $previous + $delta)), 2);
    }
}
