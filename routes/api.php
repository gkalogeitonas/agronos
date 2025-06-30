<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DeviceAuthController;
// use App\Http\Controllers\DeviceDataController;
// use App\Http\Controllers\DeviceStatusController;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
})->name('api.test');


// API routes for IoT device API calls
Route::prefix('devices')->group(function () {
    // First communication from device after user registration
    Route::post('/register', [DeviceAuthController::class, 'register'])
        ->name('api.devices.register');

    // Authenticate device and get new token
    Route::post('/auth', [DeviceAuthController::class, 'authenticate'])
        ->name('api.devices.authenticate');

    // // Protected routes requiring device authentication
    // Route::middleware('auth:sanctum')->group(function () {
    //     // Submit sensor readings
    //     Route::post('/data', [DeviceDataController::class, 'store'])
    //         ->name('api.devices.data.store');

    //     // Update device status
    //     Route::post('/status', [DeviceStatusController::class, 'update'])
    //         ->name('api.devices.status.update');
    // });
});

// Device login route
Route::post('/device/login', [DeviceAuthController::class, 'login']);
