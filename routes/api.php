<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DeviceAuthController;
use App\Http\Controllers\Api\V1\DeviceDataController;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
})->name('api.test');

Route::prefix('v1')->group(function () {
    // Device login route
    Route::post('/device/login', [DeviceAuthController::class, 'login']);
    // Device data post route (requires auth:sanctum for device tokens)
    Route::middleware('auth:sanctum')->post('/device/data', [DeviceDataController::class, 'store']);
    // ...move other v1 API routes here as needed...
});
