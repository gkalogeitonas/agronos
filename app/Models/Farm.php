<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Farm extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'location',
        'size',
        'coordinates',
        'description',
    ];

    protected $casts = [
        'coordinates' => 'array',
    ];

    protected $appends = ['center'];

    /**
     * Get the user that owns the farm.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAreaAttribute(): float|int
    {
        if (! $this->coordinates || ! isset($this->coordinates['coordinates'][0])) {
            return 0;
        }

        // Calculate area using turf.js port or geometry library
        return $this->calculatePolygonArea($this->coordinates['coordinates'][0]);
    }

    /**
     * @return array{lng: float, lat: float}|null
     */
    public function getCenterAttribute(): ?array
    {
        if (! $this->coordinates || ! isset($this->coordinates['coordinates'][0])) {
            return null;
        }
        $coords = $this->coordinates['coordinates'][0];
        $count = count($coords);
        if ($count === 0) {
            return null;
        }
        $sum = array_reduce($coords, function ($acc, $p) {
            return [$acc[0] + $p[0], $acc[1] + $p[1]];
        }, [0, 0]);

        return [
            'lng' => $sum[0] / $count,
            'lat' => $sum[1] / $count,
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * The sensors of the devices on this farm: a sensor takes its farm from its device (ADR-0008).
     *
     * @return HasManyThrough<Sensor, Device, $this>
     */
    public function sensors(): HasManyThrough
    {
        return $this->hasManyThrough(Sensor::class, Device::class);
    }

    /**
     * The alerts of the devices on this farm. A device alert has no farm of its own, so it follows its device (ADR-0008).
     *
     * @return HasManyThrough<Alert, Device, $this>
     */
    public function alerts(): HasManyThrough
    {
        return $this->hasManyThrough(Alert::class, Device::class, secondKey: 'subject_id')
            ->where('alerts.subject_type', (new Device)->getMorphClass());
    }
}
