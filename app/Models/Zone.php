<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named part of one farm that groups some of its sensors (ADR-0009). It never moves to another farm.
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * The prefix of the automatic name a zone gets when it is given none.
     */
    public const AUTOMATIC_NAME_PREFIX = 'Zone ';

    protected $fillable = [
        'user_id',
        'farm_id',
        'name',
    ];

    /**
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * @return HasMany<Sensor, $this>
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    /**
     * What has grown, grows or will grow on the zone. Deleting the zone deletes them.
     *
     * @return HasMany<Planting, $this>
     */
    public function plantings(): HasMany
    {
        return $this->hasMany(Planting::class);
    }
}
