<?php

namespace App\Services;

use App\Interfaces\TripInterface;
use App\Models\Trip;
use App\Models\User;
use App\DTO\Trip\Invite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Auth\Access\AuthorizationException;

class TripService implements TripInterface
{
    // -------------------------------
    // Trip CRUD
    // -------------------------------

    /** List trips owned or joined by the user. */
    public function list(User $user): LengthAwarePaginator
    {
        return Trip::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn($q) => $q->where('trip_user.user_id', $user->id))
            ->with(['members:id,name,email'])
            ->latest()
            ->paginate(10);
    }

    /** Create a new trip. */
    public function create(array $data, User $owner): Trip
    {
        return Trip::create([
            'name'       => $data['name'],
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date'] ?? null,
            'owner_id'   => $owner->id,
        ]);
    }

    /** Update an existing trip. */
    public function update(array $data, Trip $trip): Trip
    {
        $trip->update($data);
        return $trip->fresh();
    }

    /** Delete a trip permanently. */
    public function delete(Trip $trip): void
    {
        $trip->delete();
    }

    /** Update start location. */
    public function updateStartLocation(array $data, Trip $trip): Trip
    {
        $trip->update([
            'start_latitude'  => $data['start_latitude'],
            'start_longitude' => $data['start_longitude'],
        ]);

        return $trip->fresh();
    }

    // -------------------------------
    // Invitations management
    // -------------------------------

    /** Invite another user to the trip. */
    public function inviteUser(Trip $trip, User $actor, array $data): Invite
    {
        if (! $actor->can('manageMembers', $trip)) {
            throw new AuthorizationException('You cannot invite users to this trip.');
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $role = $data['role'] ?? 'member';

        if ($trip->owner_id === $user->id) {
            throw new \DomainException('Owner cannot invite themselves.');
        }

        $pivot = $trip->members()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if ($pivot) {
            if ($pivot->status === 'accepted') {
                throw new \DomainException('This user is already a member of the trip.');
            }

            if ($pivot->status === 'pending') {
                throw new \DomainException('This user is already invited.');
            }

            $trip->members()->updateExistingPivot($user->id, [
                'role'   => $role,
                'status' => 'pending',
            ]);
        } else {
            $trip->members()->attach($user->id, [
                'role'   => $role,
                'status' => 'pending',
            ]);
        }

        $trip->load('owner:id,name,email');

        return Invite::fromPivot($trip, $user);
    }

    /** Accept a pending invitation. */
    public function acceptInvite(Trip $trip, User $user): void
    {
        $pivot = $trip->members()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if (! $pivot) {
            throw new \DomainException('You are not invited to this trip.');
        }

        if ($pivot->status !== 'pending') {
            throw new \DomainException('Invitation is not pending.');
        }

        $trip->members()->updateExistingPivot($user->id, [
            'status' => 'accepted',
        ]);
    }

    /** Decline a pending invitation. */
    public function declineInvite(Trip $trip, User $user): void
    {
        $pivot = $trip->members()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if (! $pivot) {
            throw new \DomainException('You are not invited to this trip.');
        }

        if ($pivot->status !== 'pending') {
            throw new \DomainException('Invitation is not pending.');
        }

        $trip->members()->updateExistingPivot($user->id, [
            'status' => 'declined',
        ]);
    }

    // -------------------------------
    // Members management
    // -------------------------------

    /** List all members of the trip (including owner). */
    public function listMembers(Trip $trip): Collection
    {
        $members = $trip->members()
            ->withPivot(['role', 'status'])
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn($u) => tap($u, fn($x) => $x->is_owner = false));

        $owner = $trip->owner()
            ->get(['id', 'name', 'email'])
            ->map(function ($u) {
                $u->is_owner = true;
                $u->pivot = (object)['role' => 'owner', 'status' => 'accepted'];
                return $u;
            });

        return $owner->merge($members);
    }

    /** Update a member's role. */
    public function updateMemberRole(Trip $trip, User $user, string $role, User $actor): void
    {
        if (! $actor->can('manageMembers', $trip)) {
            throw new AuthorizationException('You cannot update member roles.');
        }

        if ($user->id === $trip->owner_id) {
            throw new \DomainException('Cannot change role of the trip owner.');
        }

        if ($actor->id === $user->id) {
            throw new \DomainException('You cannot change your own role.');
        }

        $pivot = $trip->members()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if (! $pivot) {
            throw new \DomainException('This user is not a member of the trip.');
        }

        if ($pivot->status !== 'accepted') {
            throw new \DomainException('Cannot change role of an inactive member.');
        }

        $trip->members()->updateExistingPivot($user->id, [
            'role' => $role,
        ]);
    }

    /** Remove a member from the trip. */
    public function removeMember(Trip $trip, User $user, User $actor): void
    {
        if (! $actor->can('manageMembers', $trip)) {
            throw new AuthorizationException('You cannot remove members.');
        }

        if ($user->id === $trip->owner_id) {
            throw new \DomainException('Cannot remove the trip owner.');
        }

        if ($actor->id === $user->id) {
            throw new \DomainException('You cannot remove yourself.');
        }

        $pivot = $trip->members()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if (! $pivot) {
            throw new \DomainException('This user is not a member of the trip.');
        }

        if ($pivot->status !== 'accepted') {
            throw new \DomainException('Only active members can be removed.');
        }

        $trip->members()->detach($user->id);
    }

    // -------------------------------
    // Invitation listings
    // -------------------------------

    /** List invitations for the authenticated user (pending only). */
    public function listUserInvites(User $user): Collection
    {
        return $user->joinedTrips()
            ->with(['owner:id,name,email'])
            ->wherePivot('status', 'pending')
            ->get(['trips.id', 'trips.name', 'trips.start_date', 'trips.end_date', 'trips.owner_id'])
            ->map(fn(Trip $trip) => Invite::fromModel($trip));
    }

    /** List invitations sent by the owner (pending only). */
    public function listSentInvites(User $owner): Collection
    {
        return Trip::where('owner_id', $owner->id)
            ->with(['members' => fn($q) => $q->wherePivot('status', 'pending')])
            ->get()
            ->flatMap(fn(Trip $trip) =>
            $trip->members->map(fn($m) => Invite::fromPivot($trip, $m))
            );
    }
}
