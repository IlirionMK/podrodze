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
use DomainException;

class TripUserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TripInterface $tripService
    ) {}

    /**
     * @group Members
     * @authenticated
     * @operationId listTripMembers
     *
     * List all members of the trip (including owner).
     *
     * @urlParam trip_id integer required The ID of the trip. Example: 12
     *
     * @response 200 {
     *   "data": [...]
     * }
     */
    public function index(Request $request, Trip $trip_id): JsonResponse
    {
        $this->authorize('view', $trip_id);

        $members = $this->tripService->listMembers($trip_id);

        return response()->json([
            'data' => TripUserResource::collection($members)->resolve()
        ]);
    }

    /**
     * @group Members_Invites
     * @authenticated
     * @operationId inviteUserToTrip
     *
     * Invite a user to a trip.
     *
     * @urlParam trip_id integer required Example: 5
     *
     * @bodyParam email string required Email of invited user.
     * @bodyParam role string Role of invited user (member/editor).
     * @bodyParam message string Optional invitation message.
     *
     * @response 200 { "message": "Invitation sent successfully.", "data": {...} }
     * @response 400 { "error": "User is already a member" }
     */
    public function invite(InviteTripRequest $request, Trip $trip_id): JsonResponse
    {
        $this->authorize('update', $trip_id);

        try {
            $invite = $this->tripService->inviteUser(
                $trip_id,
                $request->user(),
                $request->validated()
            );
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'data' => (new InviteResource($invite))->resolve()
        ]);
    }

    /**
     * @group Members
     * @authenticated
     * @operationId updateTripMemberRole
     *
     * Update a member's role in a trip.
     *
     * @urlParam trip_id integer required Example: 7
     * @urlParam user_id integer required Example: 44
     *
     * @bodyParam role string required One of: member, editor.
     *
     * @response 200 { "message": "Role updated." }
     * @response 400 { "error": "Cannot update owner role" }
     */
    public function update(Request $request, Trip $trip_id, User $user_id): JsonResponse
    {
        $this->authorize('update', $trip_id);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['member', 'editor'])],
        ]);

        try {
            $this->tripService->updateMemberRole(
                $trip_id,
                $user_id,
                $validated['role'],
                $request->user()
            );
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Role updated.']);
    }

    /**
     * @group Members
     * @authenticated
     * @operationId removeTripMember
     *
     * Remove a member from a trip.
     *
     * @urlParam trip_id integer required Example: 10
     * @urlParam user_id integer required Example: 18
     *
     * @response 200 { "message": "Member removed." }
     * @response 400 { "error": "You cannot remove the owner" }
     */
    public function destroy(Request $request, Trip $trip_id, User $user_id): JsonResponse
    {
        $this->authorize('update', $trip_id);

        try {
            $this->tripService->removeMember(
                $trip_id,
                $user_id,
                $request->user()
            );
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Member removed.']);
    }

    /**
     * @group Members_Invites
     * @authenticated
     * @operationId acceptTripInvite
     *
     * Accept an invitation to join a trip.
     *
     * @urlParam trip_id integer required Example: 5
     *
     * @response 200 { "message": "Invitation accepted." }
     * @response 400 { "error": "Invitation already processed" }
     */
    public function accept(Request $request, Trip $trip_id): JsonResponse
    {
        $this->authorize('accept', $trip_id);

        try {
            $this->tripService->acceptInvite($trip_id, $request->user());
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Invitation accepted.']);
    }

    /**
     * @group Members_Invites
     * @authenticated
     * @operationId declineTripInvite
     *
     * Decline an invitation.
     *
     * @urlParam trip_id integer required Example: 9
     *
     * @response 200 { "message": "Invitation declined." }
     * @response 400 { "error": "Invitation already processed" }
     */
    public function decline(Request $request, Trip $trip_id): JsonResponse
    {
        $this->authorize('decline', $trip_id);

        try {
            $this->tripService->declineInvite($trip_id, $request->user());
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Invitation declined.']);
    }

    /**
     * @group Members_Invites
     * @authenticated
     * @operationId listMyInvites
     *
     * List invitations received by the current user.
     *
     * @response 200 {
     *   "data": [...]
     * }
     */
    public function myInvites(Request $request): JsonResponse
    {
        $invites = $this->tripService->listUserInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($invites)->resolve()
        ]);
    }

    /**
     * @group Members_Invites
     * @authenticated
     * @operationId listSentInvites
     *
     * List invitations sent by the current user.
     *
     * @response 200 {
     *   "data": [...]
     * }
     */
    public function sentInvites(Request $request): JsonResponse
    {
        $sent = $this->tripService->listSentInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($sent)->resolve()
        ]);
    }
}
