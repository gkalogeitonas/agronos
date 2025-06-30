<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DeviceAuthController;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
})->name('api.test');

Route::prefix('v1')->group(function () {
    // Device login route
    Route::post('/device/login', [DeviceAuthController::class, 'login']);
    // ...move other v1 API routes here as needed...
});
