<?php

namespace App\Services;

use App\Models\Trip;

class TripInvitationService
{
    public function invite(Trip $trip, int $userId, string $role = 'member'): array
    {
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
}
