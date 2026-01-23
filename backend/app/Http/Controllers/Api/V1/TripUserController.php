<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteTripRequest;
use App\Http\Resources\TripUserResource;
use App\Http\Resources\InviteResource;
use App\Interfaces\TripInterface;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use DomainException;

class TripUserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TripInterface $tripService
    ) {}

    public function index(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $members = $this->tripService->listMembers($trip);

        return response()->json([
            'data' => TripUserResource::collection($members)->resolve(),
        ]);
    }

    public function invite(InviteTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('manageMembers', $trip);

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
            'data' => (new InviteResource($invite))->resolve(),
        ]);
    }

    public function update(Request $request, Trip $trip, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $trip);

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

    public function destroy(Request $request, Trip $trip, User $user): JsonResponse
    {
        $auth = $request->user();
        $isSelf = (int) $auth->id === (int) $user->id;

        if (!$isSelf) {
            $this->authorize('manageMembers', $trip);
        } else {
            $this->authorize('view', $trip);
        }

        try {
            $this->tripService->removeMember(
                $trip,
                $user,
                $auth
            );
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Member removed.']);
    }

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

    public function myInvites(Request $request): JsonResponse
    {
        $invites = $this->tripService->listUserInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($invites)->resolve(),
        ]);
    }

    public function sentInvites(Request $request): JsonResponse
    {
        $sent = $this->tripService->listSentInvites($request->user());

        return response()->json([
            'data' => InviteResource::collection($sent)->resolve(),
        ]);
    }
}
