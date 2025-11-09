<?php

namespace App\Services;

use App\Interfaces\TripInterface;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TripService implements TripInterface
{
    // --- Trip CRUD ---
    public function list(Request $request): LengthAwarePaginator
    {
        $user = $request->user();

        return Trip::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn($q) => $q->where('trip_user.user_id', $user->id))
            ->with(['members:id,name,email'])
            ->latest()
            ->paginate(10);
    }

    public function create(Request $request): Trip
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return Trip::create([
            'name'       => $data['name'],
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date'] ?? null,
            'owner_id'   => $request->user()->id,
        ]);
    }

    public function update(Request $request, Trip $trip): Trip
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date'   => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $trip->update($data);
        return $trip->fresh();
    }

    public function delete(Trip $trip): void
    {
        $trip->delete();
    }

    public function updateStartLocation(Request $request, Trip $trip): array
    {
        $data = $request->validate([
            'start_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'start_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $trip->update($data);

        return [
            'trip_id'         => $trip->id,
            'start_latitude'  => $trip->start_latitude,
            'start_longitude' => $trip->start_longitude,
            'message'         => 'Start location updated successfully.',
        ];
    }

    // --- Invites (TripController) ---
    public function invite(Request $request, Trip $trip): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['sometimes', 'string', 'in:member,editor'],
        ]);

        $userId = (int)$data['user_id'];
        $role   = $data['role'] ?? 'member';

        if ($trip->owner_id === $userId) {
            return ['status' => 400, 'body' => ['message' => 'Owner is already a member']];
        }

        $existing = $trip->members()
            ->withPivot(['role', 'status'])
            ->where('users.id', $userId)
            ->first();

        if ($existing) {
            $status = $existing->pivot->status ?? null;

            if ($status === 'accepted') {
                return ['status' => 200, 'body' => ['message' => 'Already a member']];
            }

            if ($status === 'pending') {
                return ['status' => 200, 'body' => ['message' => 'Invite already pending']];
            }

            $trip->members()->updateExistingPivot($userId, [
                'role'   => $role,
                'status' => 'pending',
            ]);

            return ['status' => 200, 'body' => ['message' => 'Invite re-sent (pending)', 'status' => 'pending']];
        }

        $trip->members()->syncWithoutDetaching([
            $userId => ['role' => $role, 'status' => 'pending'],
        ]);

        return ['status' => 201, 'body' => ['message' => 'User invited', 'status' => 'pending']];
    }

    public function accept(Trip $trip): array
    {
        $user = auth()->user();
        $trip->members()->updateExistingPivot($user->id, ['status' => 'accepted']);
        return ['status' => 200, 'body' => ['message' => 'Invitation accepted']];
    }

    public function decline(Trip $trip): array
    {
        $user = auth()->user();
        $trip->members()->updateExistingPivot($user->id, ['status' => 'declined']);
        return ['status' => 200, 'body' => ['message' => 'Invitation declined']];
    }

    // --- Members management ---
    public function listMembers(Trip $trip): Collection
    {
        $members = $trip->members()
            ->withPivot(['role', 'status'])
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

    public function updateMemberRole(Trip $trip, User $user, string $role, ?User $actor = null): array
    {
        $trip->members()->updateExistingPivot($user->id, ['role' => $role]);
        return ['status' => 200, 'body' => ['message' => 'Role updated']];
    }

    public function removeMember(Trip $trip, User $user, ?User $actor = null): array
    {
        $trip->members()->detach($user->id);
        return ['status' => 200, 'body' => ['message' => 'Member removed']];
    }

    // --- Extended Invites (TripUserController) ---
    public function inviteUser(Trip $trip, User $actor, array $data): array
    {
        $userId = (int)$data['user_id'];
        $role = $data['role'] ?? 'member';

        if ($trip->owner_id === $userId) {
            return ['status' => 400, 'body' => ['message' => 'Owner is already a member']];
        }

        $existing = $trip->members()->withPivot(['role', 'status'])->where('users.id', $userId)->first();

        if ($existing) {
            $status = $existing->pivot->status ?? null;

            if ($status === 'accepted') {
                return ['status' => 200, 'body' => ['message' => 'Already a member']];
            }

            if ($status === 'pending') {
                return ['status' => 200, 'body' => ['message' => 'Invite already pending']];
            }

            $trip->members()->updateExistingPivot($userId, [
                'role' => $role,
                'status' => 'pending',
            ]);

            return ['status' => 200, 'body' => ['message' => 'Invite re-sent (pending)', 'status' => 'pending']];
        }

        $trip->members()->syncWithoutDetaching([$userId => ['role' => $role, 'status' => 'pending']]);
        return ['status' => 201, 'body' => ['message' => 'User invited', 'status' => 'pending']];
    }

    public function acceptInvite(Trip $trip, User $user): array
    {
        $pivot = $trip->members()->where('users.id', $user->id)->first()?->pivot;

        if (!$pivot || $pivot->status !== 'pending') {
            return ['status' => 403, 'body' => ['message' => 'No pending invite']];
        }

        $trip->members()->updateExistingPivot($user->id, ['status' => 'accepted']);
        return ['status' => 200, 'body' => ['message' => 'Invite accepted']];
    }

    public function declineInvite(Trip $trip, User $user): array
    {
        $pivot = $trip->members()->where('users.id', $user->id)->first()?->pivot;

        if (!$pivot || $pivot->status !== 'pending') {
            return ['status' => 403, 'body' => ['message' => 'No pending invite']];
        }

        $trip->members()->updateExistingPivot($user->id, ['status' => 'declined']);
        return ['status' => 200, 'body' => ['message' => 'Invite declined']];
    }

    // --- Lists (User Invitations) ---
    public function listUserInvites(User $user): Collection
    {
        return $user->joinedTrips()
            ->with(['owner:id,name,email'])
            ->get(['trips.id', 'trips.name', 'trips.start_date', 'trips.end_date', 'trips.owner_id'])
            ->map(function ($trip) {
                return [
                    'trip_id'    => $trip->id,
                    'name'       => $trip->name,
                    'start_date' => $trip->start_date,
                    'end_date'   => $trip->end_date,
                    'role'       => $trip->pivot->role,
                    'status'     => $trip->pivot->status,
                    'owner'      => $trip->owner,
                ];
            });
    }

    public function listSentInvites(User $owner): Collection
    {
        $trips = $owner->trips()->with(['members:id,name,email'])->get();

        return collect($trips)->flatMap(function ($trip) {
            return $trip->members->map(fn($m) => [
                'trip_id'      => $trip->id,
                'trip_name'    => $trip->name,
                'start_date'   => $trip->start_date,
                'end_date'     => $trip->end_date,
                'invited_user' => [
                    'id'    => $m->id,
                    'name'  => $m->name,
                    'email' => $m->email,
                ],
                'role'   => $m->pivot->role,
                'status' => $m->pivot->status,
            ]);
        });
    }
}
