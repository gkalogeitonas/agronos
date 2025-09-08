<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorReadingEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $sensorId, public array $payload = [])
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("sensor.{$this->sensorId}");
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
