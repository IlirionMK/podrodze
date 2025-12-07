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

/**
 * @OA\Tag(
 * name="Members",
 * description="Trip member management (listing, roles, removal)."
 * )
 * @OA\Tag(
 * name="Members_Invites",
 * description="Trip invitation management (sending, accepting, declining, viewing)."
 * )
 */
class TripUserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TripInterface $tripService
    ) {}

    /**
     * @OA\Get(
     * path="/trips/{trip}/members",
     * summary="List all members of the trip (including owner).",
     * tags={"Members"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=12)
     * ),
     * @OA\Response(
     * response=200,
     * description="Successful operation.",
     * @OA\JsonContent(
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="John Doe"),
     * @OA\Property(property="role", type="string", enum={"owner", "editor", "member"}, example="owner")
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $members = $this->tripService->listMembers($trip);

        return response()->json([
            'data' => TripUserResource::collection($members)->resolve()
        ]);
    }

    /**
     * @OA\Post(
     * path="/trips/{trip}/members/invite",
     * summary="Invite a user to join a trip.",
     * tags={"Members_Invites"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=5)
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"email"},
     * @OA\Property(property="email", type="string", format="email", description="Email of the invited user.", example="user@example.com"),
     * @OA\Property(property="role", type="string", nullable=true, enum={"member", "editor"}, description="Role assigned to user.", example="member"),
     * @OA\Property(property="message", type="string", nullable=true, description="Optional message.", example="Join our trip!")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Invitation sent successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Invitation sent successfully."),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=9),
     * @OA\Property(property="email", type="string", example="user@example.com"),
     * @OA\Property(property="status", type="string", example="pending")
     * )
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Bad Request (User is already a member or other domain error).",
     * @OA\JsonContent(
     * @OA\Property(property="error", type="string", example="User is already a member")
     * )
     * ),
     * @OA\Response(response=422, description="Validation error.")
     * )
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
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'data' => (new InviteResource($invite))->resolve()
        ]);
    }

    /**
     * @OA\Put(
     * path="/trips/{trip}/members/{user}",
     * summary="Update the role of a trip member.",
     * tags={"Members"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=7)
     * ),
     * @OA\Parameter(
     * name="user",
     * in="path",
     * required=true,
     * description="The ID of the user to update.",
     * @OA\Schema(type="integer", example=44)
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"role"},
     * @OA\Property(property="role", type="string", enum={"member", "editor"}, description="New role assigned to the user.", example="editor")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Role updated successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Role updated.")
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Bad Request (Cannot update owner role).",
     * @OA\JsonContent(
     * @OA\Property(property="error", type="string", example="Cannot update owner role")
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=422, description="Validation error.")
     * )
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
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Role updated.']);
    }

    /**
     * @OA\Delete(
     * path="/trips/{trip}/members/{user}",
     * summary="Remove a member from a trip.",
     * tags={"Members"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=10)
     * ),
     * @OA\Parameter(
     * name="user",
     * in="path",
     * required=true,
     * description="The ID of the user to remove.",
     * @OA\Schema(type="integer", example=18)
     * ),
     * @OA\Response(
     * response=200,
     * description="Member successfully removed.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Member removed.")
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Bad Request (Cannot remove owner).",
     * @OA\JsonContent(
     * @OA\Property(property="error", type="string", example="You cannot remove the owner")
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden")
     * )
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
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Member removed.']);
    }

    /**
     * @OA\Post(
     * path="/trips/{trip}/accept",
     * summary="Accept a trip invitation.",
     * tags={"Members_Invites"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip invitation to accept.",
     * @OA\Schema(type="integer", example=5)
     * ),
     * @OA\Response(
     * response=200,
     * description="Invitation accepted successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Invitation accepted.")
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Bad Request (Invitation already processed or not found).",
     * @OA\JsonContent(
     * @OA\Property(property="error", type="string", example="Invitation already processed")
     * )
     * )
     * )
     */
    public function accept(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('accept', $trip);

        try {
            $this->tripService->acceptInvite($trip, $request->user());
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Invitation accepted.']);
    }

    /**
     * @OA\Post(
     * path="/trips/{trip}/decline",
     * summary="Decline a trip invitation.",
     * tags={"Members_Invites"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip invitation to decline.",
     * @OA\Schema(type="integer", example=9)
     * ),
     * @OA\Response(
     * response=200,
     * description="Invitation declined successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Invitation declined.")
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Bad Request (Invitation already processed or not found).",
     * @OA\JsonContent(
     * @OA\Property(property="error", type="string", example="Invitation already processed")
     * )
     * )
     * )
     */
    public function decline(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('decline', $trip);

        try {
            $this->tripService->declineInvite($trip, $request->user());
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Invitation declined.']);
    }

    /**
     * @OA\Get(
     * path="/users/me/invites",
     * summary="List invitations received by the current user.",
     * tags={"Members_Invites"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Successful operation.",
     * @OA\JsonContent(
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="trip", type="string", example="Weekend Trip"),
     * @OA\Property(property="status", type="string", example="pending")
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myInvites(Request $request): JsonResponse
    {
        $invites = $this->tripService->listUserInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($invites)->resolve()
        ]);
    }

    /**
     * @OA\Get(
     * path="/users/me/invites/sent",
     * summary="List invitations sent by the current user.",
     * tags={"Members_Invites"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Successful operation.",
     * @OA\JsonContent(
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * @OA\Property(property="id", type="integer", example=2),
     * @OA\Property(property="email", type="string", example="friend@example.com"),
     * @OA\Property(property="status", type="string", example="pending")
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function sentInvites(Request $request): JsonResponse
    {
        $sent = $this->tripService->listSentInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($sent)->resolve()
        ]);
    }
}
