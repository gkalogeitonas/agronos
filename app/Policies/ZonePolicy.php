<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

class ZonePolicy
{
    /**
     * Determine whether the user can rename the zone.
     */
    public function update(User $user, Zone $zone): bool
    {
        return $user->id === $zone->user_id;
    }

    /**
     * Determine whether the user can delete the zone.
     */
    public function delete(User $user, Zone $zone): bool
    {
        return $user->id === $zone->user_id;
    }
}
