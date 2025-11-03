<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TripPolicy
{
    use HandlesAuthorization;

    /**
     * Czy użytkownik może zobaczyć podróż
     */
    public function view(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        return $trip->members()
            ->wherePivotIn('status', ['accepted', 'pending'])
            ->where('users.id', $user->id)
            ->exists();
    }

    /**
     * Czy użytkownik może modyfikować podróż
     * (właściciel lub edytor)
     */
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

    /**
     * Czy użytkownik może usunąć podróż
     * (tylko właściciel)
     */
    public function delete(User $user, Trip $trip): bool
    {
        return $trip->owner_id === $user->id;
    }

    /**
     * Czy użytkownik może zaakceptować zaproszenie
     * (musi mieć status = 'pending')
     */
    public function accept(User $user, Trip $trip): bool
    {
        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'pending')
            ->exists();
    }

    /**
     * Czy użytkownik może odrzucić zaproszenie
     * (musi mieć status = 'pending')
     */
    public function decline(User $user, Trip $trip): bool
    {
        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'pending')
            ->exists();
    }
}
