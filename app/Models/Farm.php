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

    /**
     * The named parts of this farm that group some of its sensors (ADR-0009).
     *
     * @return HasMany<Zone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    /**
     * The name a zone gets on this farm when it is given none: `Zone N`, with the lowest N not already used here.
     */
    public function nextZoneName(): string
    {
        $usedNames = $this->zones()->pluck('name')->flip();

        $number = 1;
        while ($usedNames->has(Zone::AUTOMATIC_NAME_PREFIX.$number)) {
            $number++;
        }

        return Zone::AUTOMATIC_NAME_PREFIX.$number;
    }

    /**
     * What grows on this farm: whole-farm plantings, and those of its zones (ADR-0010). Deleting the farm deletes them.
     *
     * @return HasMany<Planting, $this>
     */
    public function plantings(): HasMany
    {
        return $this->hasMany(Planting::class);
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
