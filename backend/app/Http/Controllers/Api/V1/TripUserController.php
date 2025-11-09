<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TripUserResource;
use App\Http\Resources\TripResource;
use App\Http\Resources\InviteResource;
use App\Interfaces\TripInterface;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;

class TripUserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TripInterface $tripService
    ) {}

    /**
     * @group Members
     *
     * Get members of a trip.
     *
     * Returns all trip members (including owner).
     */
    public function index(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $members = $this->tripService->listMembers($trip);

        return response()->json([
            'data' => TripUserResource::collection($members),
        ]);
    }

    /**
     * @group Members / Invites
     *
     * Invite a user to a trip.
     */
    public function invite(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$trip->owner_id ?? null])],
            'role'    => ['required', Rule::in(['member', 'editor'])],
            'resend'  => ['sometimes', 'boolean'],
        ]);

        $result = $this->tripService->inviteUser($trip, $request->user(), $data);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * @group Members
     *
     * Update member role.
     */
    public function update(Request $request, Trip $trip, User $user): JsonResponse
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'role' => ['required', Rule::in(['member', 'editor'])],
        ]);

        $result = $this->tripService->updateMemberRole($trip, $user, $data['role'], $request->user());

        return response()->json($result['body'], $result['status']);
    }

    /**
     * @group Members
     *
     * Remove a member from the trip.
     */
    public function destroy(Request $request, Trip $trip, User $user): JsonResponse
    {
        $this->authorize('update', $trip);

        $result = $this->tripService->removeMember($trip, $user, $request->user());

        return response()->json($result['body'], $result['status']);
    }

    /**
     * @group Members / Invites
     *
     * Accept an invitation.
     */
    public function accept(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('accept', $trip);

        $result = $this->tripService->acceptInvite($trip, $request->user());

        return response()->json($result['body'], $result['status']);
    }

    /**
     * @group Members / Invites
     *
     * Decline an invitation.
     */
    public function decline(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('decline', $trip);

        $result = $this->tripService->declineInvite($trip, $request->user());

        return response()->json($result['body'], $result['status']);
    }

    /**
     * @group Members / Invites
     *
     * List user's invitations.
     *
     * Returns all trips where the authenticated user is invited.
     */
    public function myInvites(Request $request): JsonResponse
    {
        $invites = $this->tripService->listUserInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($invites),
        ]);
    }

    /**
     * @group Members / Invites
     *
     * List sent invitations (owner view).
     *
     * Returns all invitations sent by the current user (trip owner).
     */
    public function sentInvites(Request $request): JsonResponse
    {
        $sent = $this->tripService->listSentInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($sent),
        ]);
    }
}
