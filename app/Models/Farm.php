<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Farm extends Model
{
    use HasFactory;

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

    /**
     * Get the user that owns the farm.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
