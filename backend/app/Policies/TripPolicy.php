<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TripPolicy
{
    use HandlesAuthorization;

    /**
     * Grant all abilities to administrators (optional).
     */
    public function before(User $user, string $ability): ?bool
    {
        if (property_exists($user, 'is_admin') && $user->is_admin) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the trip.
     * Allowed: owner or member (accepted or pending invite).
     */
    public function view(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        // Optimization: if 'members' relation already loaded, avoid extra SQL
        if ($trip->relationLoaded('members')) {
            return $trip->members->contains(fn ($m) =>
                $m->id === $user->id && in_array($m->pivot->status, ['accepted', 'pending'])
            );
        }

        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivotIn('status', ['accepted', 'pending'])
            ->exists();
    }

    /**
     * Determine whether the user can update the trip.
     * Allowed: owner or editor.
     */
    public function update(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        // Optimization: check loaded relation if available
        if ($trip->relationLoaded('members')) {
            return $trip->members->contains(fn ($m) =>
                $m->id === $user->id && $m->pivot->role === 'editor'
            );
        }

        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'editor')
            ->exists();
    }

    /**
     * Determine whether the user can delete the trip.
     * Allowed: only owner.
     */
    public function delete(User $user, Trip $trip): bool
    {
        return $trip->owner_id === $user->id;
    }

    /**
     * Determine whether the user can create a trip.
     * Currently open to all authenticated users.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Shared helper — check if user can respond to an invite.
     * (status must be 'pending')
     */
    protected function canRespond(User $user, Trip $trip): bool
    {
        // Optimization: if relation already loaded, check in-memory
        if ($trip->relationLoaded('members')) {
            return $trip->members->contains(fn ($m) =>
                $m->id === $user->id && $m->pivot->status === 'pending'
            );
        }

        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'pending')
            ->exists();
    }

    /**
     * Determine whether the user can accept an invitation.
     */
    public function accept(User $user, Trip $trip): bool
    {
        return $this->canRespond($user, $trip);
    }

    /**
     * Determine whether the user can decline an invitation.
     */
    public function decline(User $user, Trip $trip): bool
    {
        return $this->canRespond($user, $trip);
    }
}
