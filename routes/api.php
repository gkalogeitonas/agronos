<?php

use Illuminate\Support\Facades\Route;


Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
})->name('api.test');


// api.php routes - for IoT device API calls
Route::prefix('api/devices')->group(function () {
    // First communication from device after user registration
    Route::post('/register', [DeviceAuthController::class, 'register'])
        ->name('api.devices.register');

    // Authenticate device and get new token
    Route::post('/auth', [DeviceAuthController::class, 'authenticate'])
        ->name('api.devices.authenticate');

    // Protected routes requiring device authentication
    Route::middleware('auth:device-token')->group(function () {
        // Submit sensor readings
        Route::post('/data', [DeviceDataController::class, 'store'])
            ->name('api.devices.data.store');

        // Update device status
        Route::post('/status', [DeviceStatusController::class, 'update'])
            ->name('api.devices.status.update');
    });
});
