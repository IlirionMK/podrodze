<?php

namespace App\Interfaces;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TripInterface
{
    // --- Trip CRUD ---
    public function list(Request $request): LengthAwarePaginator;
    public function create(Request $request): Trip;
    public function update(Request $request, Trip $trip): Trip;
    public function delete(Trip $trip): void;
    public function updateStartLocation(Request $request, Trip $trip): array;

    // --- Members management ---
    public function listMembers(Trip $trip): Collection;
    public function updateMemberRole(Trip $trip, User $user, string $role, ?User $actor = null): array;
    public function removeMember(Trip $trip, User $user, ?User $actor = null): array;

    // --- Invitations ---
    public function inviteUser(Trip $trip, User $actor, array $data): array;
    public function acceptInvite(Trip $trip, User $user): array;
    public function declineInvite(Trip $trip, User $user): array;

    // --- Invitation listings ---
    public function listUserInvites(User $user): Collection;
    public function listSentInvites(User $owner): Collection;
}
