<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TripPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Trip $trip): bool
    {
        return $trip->owner_id === $user->id
            || $trip->members()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'editor')
            ->exists();
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $trip->owner_id === $user->id;
    }
}
