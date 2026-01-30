<?php

use App\Http\Controllers\Api\V1\DeviceAuthController;
use App\Http\Controllers\Api\V1\DeviceDataController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
})->name('api.test');

Route::prefix('v1')->group(function () {
    // Device login route
    Route::post('/device/login', [DeviceAuthController::class, 'login']);
    // Device data post route (requires auth:sanctum for device tokens)
    Route::middleware('auth:sanctum')->post('/device/data', [DeviceDataController::class, 'store']);
    // Device can request/provision MQTT credentials after authenticating
    Route::middleware('auth:sanctum')->get('/device/mqtt-credentials', [DeviceAuthController::class, 'provisionMqttCredentials']);
    // ...move other v1 API routes here as needed...
    // Route::middleware('auth:sanctum')->post('/device/mqtt-webhook', [DeviceDataController::class, 'mqttBrokerWebhook']);
    Route::post('/device/mqtt-webhook', [DeviceDataController::class, 'mqttBrokerWebhook']);
});
