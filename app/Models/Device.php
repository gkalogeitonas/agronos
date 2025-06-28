<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Device extends Model
{
    use HasFactory;

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
