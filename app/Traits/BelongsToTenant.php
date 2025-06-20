<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * The "booted" method of the trait.
     *
     * @return void
     */
    protected static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope);

        // Automatically set user_id on model creation if not explicitly set
        static::creating(function (Model $model) {
            if (!$model->user_id && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }

    /**
     * Get all models without tenant scope applied.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function allTenants()
    {
        return static::withoutGlobalScope(TenantScope::class);
    }

    /**
     * Get models for a specific tenant.
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function forTenant($userId)
    {
        return static::withoutGlobalScope(TenantScope::class)->where('user_id', $userId);
    }

    /**
     * Define a relationship to the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
