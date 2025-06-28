<?php

use App\Http\Controllers\FarmController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DeviceRegistrationController;
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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Device registration and management routes
Route::middleware(['auth', 'verified'])->group(function () {
    // User scans QR code and registers device
    Route::post('/devices/register-by-user', [DeviceRegistrationController::class, 'registerByUser'])
        ->name('devices.register-by-user');

    // Device management page
    Route::get('/devices', [DeviceController::class, 'index'])
        ->name('devices.index');

    // View specific device details
    Route::get('/devices/{device}', [DeviceController::class, 'show'])
        ->name('devices.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
