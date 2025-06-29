<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;

class AppSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('settings/app/Index');
    }
}
