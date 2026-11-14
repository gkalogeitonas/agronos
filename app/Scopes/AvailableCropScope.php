<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * For a signed-in user, limits crops to the catalogue (no owner) plus the user's own (ADR-0010).
 */
class AvailableCropScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $builder->where(function (Builder $query) use ($model) {
                $query->whereNull($model->qualifyColumn('user_id'))
                    ->orWhere($model->qualifyColumn('user_id'), Auth::id());
            });
        }
    }
}
