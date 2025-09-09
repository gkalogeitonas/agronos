<?php

use App\Events\SensorReadingEvent;
use Illuminate\Broadcasting\PrivateChannel;

test('sensor reading event broadcasts on private sensor channel and includes payload', function () {
    $event = new SensorReadingEvent(123, ['value' => 9.9, 'time' => '2025-09-09 12:00:00']);

    $channel = $event->broadcastOn();
    expect($channel)->toBeInstanceOf(PrivateChannel::class);
    // Laravel prefixes private channel names with "private-"
    expect($channel->name)->toBe('private-sensor.123');

    $data = $event->broadcastWith();
    expect($data['value'])->toBe(9.9);
    expect($data['time'])->toBe('2025-09-09 12:00:00');
});
