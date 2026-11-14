<?php

namespace App\Models;

use App\Scopes\AvailableCropScope;
use Database\Factories\CropFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A kind of plant grown. A catalogue crop has no owner and is shared; a tenant's own crop has a `user_id`.
 * It does not use `BelongsToTenant`, which would hide catalogue rows (ADR-0010).
 */
class Crop extends Model
{
    /** @use HasFactory<CropFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'eppo_code',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new AvailableCropScope);
    }

    /**
     * An `exists` rule for crop ids from input: the catalogue plus the signed-in tenant's crops.
     */
    public static function existsRule(): Exists
    {
        return Rule::exists('crops', 'id')->where(
            fn (QueryBuilder $query) => $query->where(
                fn (QueryBuilder $nested) => $nested->whereNull('user_id')->orWhere('user_id', Auth::id())
            )
        );
    }

    /**
     * @return HasMany<Planting, $this>
     */
    public function plantings(): HasMany
    {
        return $this->hasMany(Planting::class);
    }
}
