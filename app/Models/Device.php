<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Device extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory;

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
