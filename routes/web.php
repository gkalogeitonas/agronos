<?php

use App\Http\Controllers\FarmController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\DeviceController;
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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
