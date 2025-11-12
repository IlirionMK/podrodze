<?php
namespace App\Interfaces;

use App\DTO\Trip\Invite;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TripInterface
{
    // CRUD
    public function list(User $user): LengthAwarePaginator;
    public function create(array $data, User $owner): Trip;
    public function update(array $data, Trip $trip): Trip;
    public function delete(Trip $trip): void;
    public function updateStartLocation(array $data, Trip $trip): Trip;

    // Members / Invites
    public function inviteUser(Trip $trip, User $actor, array $data): Invite;
    public function acceptInvite(Trip $trip, User $user): void;
    public function declineInvite(Trip $trip, User $user): void;
    public function listMembers(Trip $trip): Collection;
    public function updateMemberRole(Trip $trip, User $user, string $role, User $actor): void;
    public function removeMember(Trip $trip, User $user, User $actor): void;

    // Invites overview
    public function listUserInvites(User $user): Collection;
    public function listSentInvites(User $owner): Collection;
}
