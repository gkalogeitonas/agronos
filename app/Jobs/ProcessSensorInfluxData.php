<?php

namespace App\Jobs;

use App\Models\Sensor;
use App\Services\InfluxDBService;
use App\Services\SensorMeasurementPayloadFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSensorInfluxData implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Sensor $sensor,
        public float $value,
        public int $timestamp
    ) {
        // Set queue name for time-series data processing
        $this->onQueue('sensor-data');
    }

    /**
     * Execute the job.
     */
    public function handle(InfluxDBService $influx): void
    {
        try {
            $payload = SensorMeasurementPayloadFactory::make($this->sensor, $this->value);

            // Override timestamp if provided (for historical data)
            if ($this->timestamp) {
                $payload['time'] = $this->timestamp;
            }

            $influx->writeArray($payload);
        } catch (\Exception $e) {
            // Log the error for monitoring
            Log::error('Failed to write sensor data to InfluxDB', [
                'sensor_id' => $this->sensor->id,
                'value' => $this->value,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Sensor InfluxDB job failed permanently', [
            'sensor_id' => $this->sensor->id,
            'value' => $this->value,
            'exception' => $exception->getMessage(),
        ]);
    }
}
