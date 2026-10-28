<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Sensor;
use App\Services\DeviceMqttPublisher;
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
use PhpMqtt\Client\Exceptions\MqttClientException;

#[Signature('devices:simulate
    {devices?* : UUIDs of the devices to simulate (defaults to every device with sensors)}
    {--via=direct : How readings are sent: "direct" (SensorDataService), "http" (device API) or "mqtt" (EMQX broker)}
    {--secret='.DevEnvironmentSeeder::DEVICE_SECRET.' : Plaintext device secret used to log in with --via=http or --via=mqtt}
    {--url= : Base URL of the device API (defaults to APP_URL)}
    {--interval=10 : Seconds between readings}
    {--once : Send a single round of readings and exit}')]
#[Description('Send fake sensor readings for virtual devices in local development')]
class SimulateDevices extends Command
{
    private const TRANSPORTS = ['direct', 'http', 'mqtt'];

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
     * Open broker connection per device id for the mqtt transport.
     *
     * @var array<int, DeviceMqttPublisher>
     */
    private array $publishers = [];

    private int $interval = 10;

    private bool $stopping = false;

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

        $this->interval = max(1, (int) $this->option('interval'));
        $this->trap([SIGINT, SIGTERM], fn () => $this->stopping = true);

        $this->info("Simulating {$devices->count()} device(s) via {$via}. Press Ctrl+C to stop.");

        try {
            while (! $this->stopping) {
                $devices = $devices->filter(fn (Device $device) => match ($via) {
                    'http' => $this->sendOverHttp($device),
                    'mqtt' => $this->sendOverMqtt($device),
                    default => $this->sendDirect($device, $sensorDataService),
                });

                if ($devices->isEmpty()) {
                    $this->error('No devices left to simulate.');

                    return self::FAILURE;
                }

                if ($this->option('once')) {
                    break;
                }

                Sleep::for($this->interval)->seconds();
            }
        } finally {
            foreach ($this->publishers as $publisher) {
                $publisher->disconnect();
            }
        }

        return self::SUCCESS;
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
                $this->warn("[{$device->uuid}] Skipped: LoRa devices send through the LoRa gateway; simulate them with --via=direct.");
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

    /**
     * Publish a round of readings to the device's MQTT topic, connecting to the broker first if needed.
     * Returns false when the device cannot get credentials or connect, so it is dropped from the simulation.
     */
    private function sendOverMqtt(Device $device): bool
    {
        $publisher = $this->publishers[$device->id] ??= $this->connectToBroker($device);

        if ($publisher === null) {
            unset($this->publishers[$device->id]);

            return false;
        }

        $payloads = $this->readingsFor($device);

        try {
            $publisher->publish("devices/{$device->uuid}/sensors", json_encode(['sensors' => $payloads]));
        } catch (MqttClientException $exception) {
            $this->warn("[{$device->uuid}] Publish failed: {$exception->getMessage()}. Reconnecting next round.");
            $publisher->disconnect();
            unset($this->publishers[$device->id]);

            return true;
        }

        $this->reportReadings($device, $payloads);

        return true;
    }

    /**
     * Log in, fetch the device's broker credentials from the device API and connect as the device.
     */
    private function connectToBroker(Device $device): ?DeviceMqttPublisher
    {
        $token = $this->tokens[$device->id] ??= $this->login($device);

        if ($token === null) {
            unset($this->tokens[$device->id]);

            return null;
        }

        $credentials = $this->api()->withToken($token)->get('api/v1/device/mqtt-credentials');

        if ($credentials->failed() || ! $credentials->json('username')) {
            $reason = $credentials->json('error') ?? $credentials->body();
            $this->warn("[{$device->uuid}] Skipped: could not get MQTT credentials (HTTP {$credentials->status()}: {$reason}).");

            return null;
        }

        $publisher = app(DeviceMqttPublisher::class);

        try {
            $publisher->connect(
                "{$device->uuid}-simulator",
                $credentials->json('username'),
                $credentials->json('password'),
                min(65535, max(60, $this->interval * 2)),
            );
        } catch (MqttClientException $exception) {
            $this->warn("[{$device->uuid}] Skipped: MQTT connection failed: {$exception->getMessage()}. Check that the EMQX user's password matches devices.mqtt_password.");

            return null;
        }

        return $publisher;
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
