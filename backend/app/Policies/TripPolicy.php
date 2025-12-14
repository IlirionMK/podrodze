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

    /**
     * Allowed: owner or member (accepted or pending invite).
     */
    public function view(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

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
     * Allowed: owner or accepted editor.
     */
    public function update(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        if ($trip->relationLoaded('members')) {
            return $trip->members->contains(fn ($m) =>
                $m->id === $user->id
                && $m->pivot->role === 'editor'
                && $m->pivot->status === 'accepted'
            );
        }

        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'editor')
            ->wherePivot('status', 'accepted')
            ->exists();
    }

    /**
     * Allowed: only owner.
     */
    public function delete(User $user, Trip $trip): bool
    {
        return $trip->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    protected function canRespond(User $user, Trip $trip): bool
    {
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

    public function accept(User $user, Trip $trip): bool
    {
        return $this->canRespond($user, $trip);
    }

    public function decline(User $user, Trip $trip): bool
    {
        return $this->canRespond($user, $trip);
    }

    /**
     * Allowed: owner or accepted editor.
     * Used for inviting / changing roles / removing members.
     */
    public function manageMembers(User $user, Trip $trip): bool
    {
        if ($trip->owner_id === $user->id) {
            return true;
        }

        if ($trip->relationLoaded('members')) {
            return $trip->members->contains(fn ($m) =>
                $m->id === $user->id
                && $m->pivot->role === 'editor'
                && $m->pivot->status === 'accepted'
            );
        }

        return $trip->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'editor')
            ->wherePivot('status', 'accepted')
            ->exists();
    }
}
