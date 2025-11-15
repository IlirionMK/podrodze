<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteTripRequest;
use App\Http\Resources\TripUserResource;
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

    public function __construct(protected TripInterface $tripService) {}

    /**
     * @group Members
     *
     * Get members of a trip (including owner).
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
    public function invite(InviteTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $invite = $this->tripService->inviteUser(
                $trip,
                $request->user(),
                $request->validated()
            );
        } catch (\DomainException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'data' => new InviteResource($invite),
            'message' => 'Invitation sent successfully.',
        ]);
    }

    /**
     * @group Members
     *
     * Update member role.
     */
    public function update(Request $request, Trip $trip, User $user): JsonResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['member', 'editor'])],
        ]);

        try {
            $this->tripService->updateMemberRole(
                $trip,
                $user,
                $validated['role'],
                $request->user()
            );
        } catch (\DomainException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        return response()->json(['message' => 'Role updated.']);
    }

    /**
     * @group Members
     *
     * Remove a member from the trip.
     */
    public function destroy(Request $request, Trip $trip, User $user): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $this->tripService->removeMember(
                $trip,
                $user,
                $request->user()
            );
        } catch (\DomainException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        return response()->json(['message' => 'Member removed.']);
    }

    /**
     * @group Members / Invites
     *
     * Accept an invitation.
     */
    public function accept(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('accept', $trip);

        try {
            $this->tripService->acceptInvite($trip, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Invitation accepted.']);
    }

    /**
     * @group Members / Invites
     *
     * Decline an invitation.
     */
    public function decline(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('decline', $trip);

        try {
            $this->tripService->declineInvite($trip, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Invitation declined.']);
    }

    /**
     * @group Members / Invites
     *
     * List user's invitations.
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
     * List sent invitations (only for trip owners).
     */
    public function sentInvites(Request $request): JsonResponse
    {
        $sent = $this->tripService->listSentInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($sent),
        ]);
    }
}
