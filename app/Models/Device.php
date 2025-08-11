<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToTenant;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
