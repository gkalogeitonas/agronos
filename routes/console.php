<?php

use App\Events\SensorReadingEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:broadcast {sensorId=1}', function (int $sensorId) {
    $this->info("Broadcasting test event for sensor ID: {$sensorId}");
    
    try {
        $payload = [
            'timestamp' => time(),
            'value' => rand(10, 30),
            'test' => true
        ];
        
        event(new SensorReadingEvent($sensorId, $payload));
        
        $this->info('Event dispatched successfully!');
    } catch (\Exception $e) {
        $this->error('Failed to broadcast event: ' . $e->getMessage());
    }
})->purpose('Test broadcasting to Reverb');
