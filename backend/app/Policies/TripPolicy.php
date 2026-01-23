<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TripPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if (property_exists($user, 'is_admin') && $user->is_admin) {
            return true;
        }

        return null;
    }

    public function view(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        return $this->hasMembership($user, $trip, ['accepted', 'pending']);
    }

    public function update(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        return $this->hasRole($user, $trip, 'editor', 'accepted');
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $trip->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function addPlace(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        return $this->hasMembership($user, $trip, ['accepted']);
    }

    public function vote(User $user, Trip $trip): bool
    {
        return $this->addPlace($user, $trip);
    }

    public function accept(User $user, Trip $trip): bool
    {
        return $this->hasMembership($user, $trip, ['pending']);
    }

    public function decline(User $user, Trip $trip): bool
    {
        return $this->hasMembership($user, $trip, ['pending']);
    }

    public function manageMembers(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        return $this->hasRole($user, $trip, 'editor', 'accepted');
    }

    public function leave(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return false;
        }

        return $this->hasMembership($user, $trip, ['accepted']);
    }

    private function hasMembership(User $user, Trip $trip, array $statuses): bool
    {
        if ($trip->relationLoaded('members')) {
            return $trip->members->contains(fn ($m) =>
                $m->id === $user->id && in_array($m->pivot->status, $statuses, true)
            );
        }

        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivotIn('status', $statuses)
            ->exists();
    }

    private function hasRole(User $user, Trip $trip, string $role, string $status): bool
    {
        if ($trip->relationLoaded('members')) {
            return $trip->members->contains(fn ($m) =>
                $m->id === $user->id && $m->pivot->role === $role && $m->pivot->status === $status
            );
        }

        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', $role)
            ->wherePivot('status', $status)
            ->exists();
    }
}
