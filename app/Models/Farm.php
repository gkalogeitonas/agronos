<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Scopes\TenantScope;
use App\Traits\BelongsToTenant;

class Farm extends Model
{
    use HasFactory, BelongsToTenant;

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



    /**
     * Get the user that owns the farm.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAreaAttribute()
    {
        if (!$this->coordinates || !isset($this->coordinates['coordinates'][0])) {
            return 0;
        }

        // Calculate area using turf.js port or geometry library
        return $this->calculatePolygonArea($this->coordinates['coordinates'][0]);
    }

    public function getCenterAttribute()
    {
        if (!$this->coordinates || !isset($this->coordinates['coordinates'][0])) {
            return null;
        }

        // Calculate centroid
        $points = $this->coordinates['coordinates'][0];
        // Implementation of centroid calculation...
    }

}
