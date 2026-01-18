<?php

namespace App\Services;

use App\DTO\Trip\Invite;
use App\Interfaces\TripInterface;
use App\Models\Trip;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TripService implements TripInterface
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {}

    public function list(User $user): LengthAwarePaginator
    {
        return Trip::query()
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                ->orWhereHas('members', function ($q) use ($user) {
                    $q->where('trip_user.user_id', $user->id)
                        ->where('trip_user.status', 'accepted');
                });
            })
            ->with(['members:id,name,email'])
            ->latest()
            ->paginate(10);
    }
    public function create(array $data, User $owner): Trip
    {
        $trip = Trip::create([
            'name'       => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date'] ?? null,
            'owner_id'   => $owner->id,
        ]);

        $this->activityLogger->add(
            actor: $owner,
            action: 'trip.created',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'name' => (string) $trip->getAttribute('name'),
                'start_date' => $trip->getAttribute('start_date'),
                'end_date' => $trip->getAttribute('end_date'),
                'owner_id' => $owner->getKey(),
            ]
        );

        return $trip;
    }

    public function update(array $data, Trip $trip): Trip
    {
        $trip->update($data);
        return $trip->fresh();
    }

    public function delete(Trip $trip): void
    {
        $trip->delete();
    }

    public function updateStartLocation(array $data, Trip $trip): Trip
    {
        $trip->update([
            'start_latitude'  => $data['start_latitude'],
            'start_longitude' => $data['start_longitude'],
        ]);

        return $trip->fresh();
    }

    public function inviteUser(Trip $trip, User $actor, array $data): Invite
    {
        if (! $actor->can('manageMembers', $trip)) {
            throw new AuthorizationException('Forbidden.');
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $role = $data['role'] ?? 'member';

        if ((int) $trip->owner_id === (int) $user->id) {
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

        $this->activityLogger->add(
            actor: $actor,
            action: 'trip.member_invited',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role,
                'status' => 'pending',
            ]
        );

        $trip->load('owner:id,name,email');

        return Invite::fromPivot($trip, $user);
    }

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

        $role = (string) $pivot->role;

        $trip->members()->updateExistingPivot($user->id, [
            'status' => 'accepted',
        ]);

        $this->activityLogger->add(
            actor: $user,
            action: 'trip.member_added',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role,
            ]
        );
    }

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

    private function roleInTrip(Trip $trip, User $user): ?string
    {
        if ((int) $user->id === (int) $trip->owner_id) {
            return 'owner';
        }

        $pivot = $trip->members()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if (! $pivot || $pivot->status !== 'accepted') {
            return null;
        }

        return $pivot->role;
    }

    public function listMembers(Trip $trip): Collection
    {
        $members = $trip->members()
            ->where('users.id', '!=', $trip->owner_id)
            ->withPivot(['role', 'status'])
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($u) => tap($u, fn ($x) => $x->is_owner = false));

        $owner = $trip->owner()
            ->get(['id', 'name', 'email'])
            ->map(function ($u) {
                $u->is_owner = true;
                $u->pivot = (object) ['role' => 'owner', 'status' => 'accepted'];
                return $u;
            });

        return $owner->merge($members);
    }

    public function updateMemberRole(Trip $trip, User $user, string $role, User $actor): void
    {
        if (! $actor->can('manageMembers', $trip)) {
            throw new AuthorizationException('Forbidden.');
        }

        if ((int) $user->id === (int) $trip->owner_id) {
            throw new AuthorizationException('Forbidden.');
        }

        if ((int) $actor->id === (int) $user->id) {
            throw new AuthorizationException('Forbidden.');
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

        $actorRole = $this->roleInTrip($trip, $actor);

        if ($actorRole === 'editor' && $pivot->role === 'editor') {
            throw new AuthorizationException('Forbidden.');
        }

        $before = (string) $pivot->role;

        $trip->members()->updateExistingPivot($user->id, [
            'role' => $role,
        ]);

        $this->activityLogger->add(
            actor: $actor,
            action: 'trip.member_role_updated',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'user_id' => $user->getKey(),
                'before' => $before,
                'after' => $role,
            ]
        );
    }

    public function removeMember(Trip $trip, User $user, User $actor): void
    {
        if (! $actor->can('manageMembers', $trip)) {
            throw new AuthorizationException('Forbidden.');
        }

        if ((int) $user->id === (int) $trip->owner_id) {
            throw new AuthorizationException('Forbidden.');
        }

        if ((int) $actor->id === (int) $user->id) {
            throw new AuthorizationException('Forbidden.');
        }

        $actorRole  = $this->roleInTrip($trip, $actor);
        $targetRole = $this->roleInTrip($trip, $user);

        if (! $actorRole) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($actorRole === 'editor' && $targetRole !== 'member') {
            throw new AuthorizationException('Forbidden.');
        }

        $trip->members()->detach($user->id);

        $this->activityLogger->add(
            actor: $actor,
            action: 'trip.member_removed',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'user_id' => $user->getKey(),
                'removed_by' => $actor->getKey(),
            ]
        );
    }

    public function listUserInvites(User $user): Collection
    {
        return $user->joinedTrips()
            ->with(['owner:id,name,email'])
            ->wherePivot('status', 'pending')
            ->get(['trips.id', 'trips.name', 'trips.start_date', 'trips.end_date', 'trips.owner_id'])
            ->map(fn (Trip $trip) => Invite::fromModel($trip));
    }

    public function listSentInvites(User $owner): Collection
    {
        return Trip::where('owner_id', $owner->id)
            ->with(['members' => fn ($q) => $q->wherePivot('status', 'pending')])
            ->get()
            ->flatMap(fn (Trip $trip) => $trip->members->map(fn ($m) => Invite::fromPivot($trip, $m)));
    }
}
