<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;

class TripUserController extends Controller
{
    use AuthorizesRequests;

    /**
     * @group Members
     *
     * Get members of a trip
     *
     * Returns all members of the trip with their role and status.
     * The owner is also included in the list with `is_owner: true` and `role: owner`.
     *
     * @authenticated
     * @urlParam trip int required Trip ID. Example: 1
     *
     * @response 200 [
     *   {
     *     "id": 14,
     *     "name": "Owner",
     *     "email": "owner@example.com",
     *     "is_owner": true,
     *     "pivot": { "role": "owner", "status": "accepted" }
     *   },
     *   {
     *     "id": 10,
     *     "name": "User2",
     *     "email": "user2@example.com",
     *     "is_owner": false,
     *     "pivot": { "role": "editor", "status": "accepted" }
     *   }
     * ]
     */
    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

        $members = $trip->members()
            ->withPivot(['role', 'status'])
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(function ($user) {
                $user->is_owner = false;
                return $user;
            });

        $owner = $trip->owner()
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                $user->is_owner = true;
                $user->pivot = (object) ['role' => 'owner', 'status' => 'accepted'];
                return $user;
            });

        return response()->json($owner->merge($members));
    }

    /**
     * @group Members / Invites
     *
     * Invite a user to a trip
     *
     * Creates an invitation for a user with a specific role (`member` or `editor`).
     * If the user previously declined the invitation (`status = declined`),
     * it can be resent using the `resend: true` parameter.
     *
     * @authenticated
     * @urlParam trip int required Trip ID. Example: 1
     * @bodyParam user_id int required User ID. Example: 15
     * @bodyParam role string required member|editor. Example: member
     * @bodyParam resend boolean optional Set to true to resend declined invitation. Example: true
     *
     * @response 201 {"message":"Invite created","trip_id":1,"user_id":15,"role":"member","status":"pending"}
     * @response 200 {"message":"Invite resent","trip_id":1,"user_id":15,"role":"member","status":"pending"}
     * @response 409 {"message":"User already invited or a member"}
     */
    public function invite(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$trip->owner_id ?? null])],
            'role'    => ['required', Rule::in(['member', 'editor'])],
            'resend'  => ['sometimes', 'boolean'],
        ]);

        $userId = (int) $data['user_id'];

        $current = $trip->members()->where('users.id', $userId)->first();

        if ($current) {
            $status = $current->pivot->status ?? null;

            // Automatically allow re-inviting if the previous invitation was declined
            if ($status === 'declined') {
                $trip->members()->updateExistingPivot($userId, [
                    'role' => $data['role'],
                    'status' => 'pending',
                ]);

                LogActivity::add($request->user(), 'invite_resent', $trip, [
                    'invited_user_id' => $userId,
                    'role'            => $data['role'],
                ]);

                return response()->json([
                    'message' => 'Invite resent',
                    'trip_id' => $trip->id,
                    'user_id' => $userId,
                    'role'    => $data['role'],
                    'status'  => 'pending',
                ], 200);
            }

            // Keep the old resend behavior if resend=true was passed explicitly
            if ($status === 'declined' && ($data['resend'] ?? false)) {
                $trip->members()->updateExistingPivot($userId, [
                    'role' => $data['role'],
                    'status' => 'pending',
                ]);

                LogActivity::add($request->user(), 'invite_resent', $trip, [
                    'invited_user_id' => $userId,
                    'role'            => $data['role'],
                ]);

                return response()->json([
                    'message' => 'Invite resent',
                    'trip_id' => $trip->id,
                    'user_id' => $userId,
                    'role'    => $data['role'],
                    'status'  => 'pending',
                ], 200);
            }

            // Otherwise, block duplicates (pending or accepted)
            return response()->json(['message' => 'User already invited or a member'], 409);
        }

        // First-time invite
        $trip->members()->attach($userId, ['role' => $data['role'], 'status' => 'pending']);

        LogActivity::add($request->user(), 'invite_created', $trip, [
            'invited_user_id' => $userId,
            'role'            => $data['role'],
        ]);

        return response()->json([
            'message' => 'Invite created',
            'trip_id' => $trip->id,
            'user_id' => $userId,
            'role'    => $data['role'],
            'status'  => 'pending',
        ], 201);
    }

    /**
     * @group Members
     *
     * Update member role
     *
     * Updates a member's role in the trip (`member` or `editor`).
     * Only owner or editor can perform this action.
     *
     * @authenticated
     * @urlParam trip int required Trip ID. Example: 1
     * @urlParam user int required User ID. Example: 10
     * @bodyParam role string required member|editor. Example: editor
     *
     * @response 200 {"message":"Role updated"}
     * @response 404 {"message":"Member not found"}
     */
    public function update(Request $request, Trip $trip, User $user)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'role' => ['required', Rule::in(['member', 'editor'])],
        ]);

        $exists = $trip->members()->where('users.id', $user->id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $trip->members()->updateExistingPivot($user->id, ['role' => $data['role']]);

        LogActivity::add($request->user(), 'role_updated', $trip, [
            'target_user_id' => $user->id,
            'new_role'       => $data['role'],
        ]);

        return response()->json(['message' => 'Role updated']);
    }

    /**
     * @group Members
     *
     * Remove a member from the trip
     *
     * Removes a member from the trip. Rules:
     * - Owner can remove anyone.
     * - Editor can remove only members with the `member` role.
     *
     * @authenticated
     * @urlParam trip int required Trip ID. Example: 1
     * @urlParam user int required User ID to remove. Example: 15
     *
     * @response 204
     * @response 403 {"message":"You cannot remove this user"}
     */
    public function destroy(Request $request, Trip $trip, User $user)
    {
        $this->authorize('update', $trip);

        $currentUser = $request->user();

        if ($user->id === $trip->owner_id) {
            return response()->json(['message' => 'You cannot remove the owner'], 403);
        }

        $currentRole = $trip->members()
            ->where('users.id', $currentUser->id)
            ->first()?->pivot?->role;

        if ($currentRole === 'editor') {
            $targetRole = $trip->members()
                ->where('users.id', $user->id)
                ->first()?->pivot?->role;

            if ($targetRole !== 'member') {
                return response()->json(['message' => 'You cannot remove this user'], 403);
            }
        }

        $trip->members()->detach($user->id);

        LogActivity::add($request->user(), 'member_removed', $trip, [
            'removed_user_id' => $user->id,
        ]);

        return response()->noContent();
    }

    /**
     * @group Members / Invites
     *
     * Accept an invitation
     *
     * Allows a user to accept their own invitation (only if `status = pending`).
     *
     * @authenticated
     * @urlParam trip int required Trip ID. Example: 1
     *
     * @response 200 {"message":"Invite accepted","trip_id":1,"user_id":15,"status":"accepted"}
     * @response 403 {"message":"No pending invite"}
     * @response 404 {"message":"Invite not found"}
     */
    public function accept(Request $request, Trip $trip)
    {
        $this->authorize('accept', $trip);

        $user = $request->user();

        $pivot = $trip->members()->where('users.id', $user->id)->first()?->pivot;
        if (!$pivot) {
            return response()->json(['message' => 'Invite not found'], 404);
        }

        if (($pivot->status ?? null) !== 'pending') {
            return response()->json(['message' => 'No pending invite'], 403);
        }

        $trip->members()->updateExistingPivot($user->id, ['status' => 'accepted']);

        LogActivity::add($request->user(), 'invite_accepted', $trip, [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Invite accepted',
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'status'  => 'accepted',
        ]);
    }

    /**
     * @group Members / Invites
     *
     * Decline an invitation
     *
     * Allows a user to decline their invitation (only if `status = pending`).
     *
     * @authenticated
     * @urlParam trip int required Trip ID. Example: 1
     *
     * @response 200 {"message":"Invite declined","trip_id":1,"user_id":15,"status":"declined"}
     * @response 403 {"message":"No pending invite"}
     * @response 404 {"message":"Invite not found"}
     */
    public function decline(Request $request, Trip $trip)
    {
        $this->authorize('decline', $trip);

        $user = $request->user();

        $pivot = $trip->members()->where('users.id', $user->id)->first()?->pivot;
        if (!$pivot) {
            return response()->json(['message' => 'Invite not found'], 404);
        }

        if (($pivot->status ?? null) !== 'pending') {
            return response()->json(['message' => 'No pending invite'], 403);
        }

        $trip->members()->updateExistingPivot($user->id, ['status' => 'declined']);

        LogActivity::add($request->user(), 'invite_declined', $trip, [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Invite declined',
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'status'  => 'declined',
        ]);
    }
    /**
     * @group Members / Invites
     *
     * List user's invitations
     *
     * Returns all trips where the authenticated user has been invited.
     * Each invitation includes trip details, role, status, and trip owner.
     *
     * @authenticated
     *
     * @response 200 [
     *   {
     *     "trip_id": 3,
     *     "name": "Weekend in Berlin",
     *     "start_date": "2025-11-10",
     *     "end_date": "2025-11-13",
     *     "role": "member",
     *     "status": "pending",
     *     "owner": {
     *       "id": 1,
     *       "name": "Owner User",
     *       "email": "owner@example.com"
     *     }
     *   }
     * ]
     */
    public function myInvites(Request $request)
    {
        $user = $request->user();

        $invites = $user->joinedTrips()
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

        return response()->json($invites);
    }
    /**
     * @group Members / Invites
     *
     * List sent invitations (owner view)
     *
     * Returns all invitations sent by the authenticated user.
     * Each entry includes invited user's name, email, role, status, and trip details.
     *
     * @authenticated
     *
     * @response 200 [
     *   {
     *     "trip_id": 3,
     *     "trip_name": "Weekend in Prague",
     *     "start_date": "2025-11-10",
     *     "end_date": "2025-11-13",
     *     "invited_user": {
     *       "id": 90,
     *       "name": "Member User",
     *       "email": "member@example.com"
     *     },
     *     "role": "member",
     *     "status": "pending"
     *   }
     * ]
     */
    public function sentInvites(Request $request)
    {
        $owner = $request->user();

        $trips = $owner->trips()->with(['members:id,name,email'])->get();

        $invitations = collect();

        foreach ($trips as $trip) {
            foreach ($trip->members as $member) {
                $invitations->push([
                    'trip_id'      => $trip->id,
                    'trip_name'    => $trip->name,
                    'start_date'   => $trip->start_date,
                    'end_date'     => $trip->end_date,
                    'invited_user' => [
                        'id'    => $member->id,
                        'name'  => $member->name,
                        'email' => $member->email,
                    ],
                    'role'   => $member->pivot->role,
                    'status' => $member->pivot->status,
                ]);
            }
        }

        return response()->json($invitations->values());
    }


}
