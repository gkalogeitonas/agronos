<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for per-sensor updates. Only allow the sensor owner or users
// who belong to the same tenant/farm depending on your app's authorization logic.
use App\Models\Sensor;

Broadcast::channel('sensor.{sensorId}', function ($user, $sensorId) {
    $sensor = Sensor::find($sensorId);
    if (! $sensor) return false;

    // Allow if the authenticated user is the owner of the sensor
    if ($sensor->user_id && (int) $sensor->user_id === (int) $user->id) {
        return true;
    }

    // Fallback: if your app has tenant/farm membership, extend this check.
    return false;
});
