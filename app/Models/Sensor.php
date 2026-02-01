<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\SensorType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sensor extends Model
{
    /** @use HasFactory<\Database\Factories\SensorFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'farm_id',
        'crop_id',
        'name',
        'uuid',
        'type',
        'lat',
        'lon',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter sensors whose related device is ONLINE.
     */
    public function scopeDeviceOnline(Builder $query): Builder
    {
        return $query->whereHas('device', function (Builder $q) {
            $q->where('status', DeviceStatus::ONLINE->value);
        });
    }

    /**
     * Scope to return sensors that are relevant for farm-facing UIs.
     * Excludes internal-only sensor types (e.g. battery).
     */
    public function scopeFarmRelevant(Builder $query): Builder
    {
        return $query->whereIn('type', SensorType::farmRelevantValues());
    }
}
