<?php

namespace App\Policies;

use App\Models\Planting;
use App\Models\User;

class PlantingPolicy
{
    /**
     * Determine whether the user can correct or end the planting.
     */
    public function update(User $user, Planting $planting): bool
    {
        return $user->id === $planting->user_id;
    }

    /**
     * Determine whether the user can delete the planting.
     */
    public function delete(User $user, Planting $planting): bool
    {
        return $user->id === $planting->user_id;
    }
}
