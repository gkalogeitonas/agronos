<?php

use App\Http\Controllers\FarmController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\SensorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

// Quick test route to dispatch FirstEvent (used to validate broadcasting)
Route::get('/test-first-event', function () {
    event(new \App\Events\FirstEvent('test from /test-first-event', ['time' => now()->toDateTimeString()]));
    return 'event dispatched';
});

// Quick test route to dispatch a private sensor event (requires auth)
Route::middleware(['auth', 'verified'])->get('/test-sensor-event/{sensor}', function (\App\Models\Sensor $sensor) {
    event(new \App\Events\SensorPrivateEvent($sensor->id, ['message' => 'private test', 'time' => now()->toDateTimeString()]));
    return 'sensor private event dispatched';
})->name('test.sensor.event');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Farm resource routes
Route::resource('farms', FarmController::class)
    ->middleware(['auth', 'verified']);

// Device registration and management routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Device management page
    Route::get('/devices', [DeviceController::class, 'index'])
        ->name('devices.index');

    //Create new device
    Route::get('/devices/create', [DeviceController::class, 'create'])
        ->name('devices.create');

    // View specific device details
    Route::get('/devices/{device}', [DeviceController::class, 'show'])
        ->name('devices.show');

    // Register device (store)
    Route::post('/devices', [DeviceController::class, 'store'])
        ->name('devices.store');

    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])
        ->name('devices.destroy');
});


// Device QR Tool route
Route::get('/tools/device-qr', function () {
    return Inertia::render('Tools/DeviceQr');
})->name('tools.device-qr');

// Sensor scan route (for web-based frontend with Inertia)
Route::middleware(['auth', 'verified'])->post('/sensors/scan', [SensorController::class, 'scan'])->name('sensors.scan');

// Sensor resource routes
Route::middleware(['auth', 'verified'])->resource('sensors', SensorController::class);

// Dedicated route for creating a sensor for a specific farm (uses the same create method)
Route::middleware(['auth', 'verified'])->get('/farms/{farm}/sensors/create', [App\Http\Controllers\SensorController::class, 'create'])->name('farms.sensors.create');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
