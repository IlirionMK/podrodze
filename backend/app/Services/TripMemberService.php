<?php


namespace App\Services;

use App\Models\Trip;
use App\Models\User;

class TripMemberService
{
    public function listWithOwner(Trip $trip)
    {
        $members = $trip->members()
            ->withPivot(['role', 'status'])
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(function ($user) {
                $user->is_owner = false;
                return $user;
            });

        $owner = $trip->owner()
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                $user->is_owner = true;
                $user->pivot = (object)['role' => 'owner', 'status' => 'accepted'];
                return $user;
            });

        return $owner->merge($members);
    }

    public function updateRole(Trip $trip, User $user, string $role): void
    {
        $trip->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    public function remove(Trip $trip, User $user): void
    {
        $trip->members()->detach($user->id);
    }

    public function isMember(Trip $trip, int $userId): bool
    {
        return $trip->members()->where('users.id', $userId)->exists();
    }
}
