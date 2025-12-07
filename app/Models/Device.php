<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToTenant;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Enums\DeviceStatus;

class Device extends Authenticatable
{
    use HasApiTokens, HasFactory, BelongsToTenant;

    protected $fillable = [
        'user_id',
        'name',
        'uuid',
        'secret',
        'type',
        'status',
        'last_seen_at',
        'battery_level',
        'signal_strength',
        'mqtt_username',
        'mqtt_password',
    ];

    protected $casts = [
        'status' => DeviceStatus::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sensors()
    {
        // Return a simplified array of sensors for frontend consumption
        return $this->hasMany(Sensor::class);
    }
}
