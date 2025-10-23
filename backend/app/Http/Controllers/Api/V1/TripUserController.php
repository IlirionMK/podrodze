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
     * Lista uczestników podróży
     *
     * Zwraca wszystkich uczestników podróży wraz z ich rolą i statusem.
     * Właściciel podróży również jest dołączany do listy z `is_owner: true` i `role: owner`.
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 1
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
     * Zaproszenie użytkownika do podróży
     *
     * Tworzy zaproszenie dla użytkownika z określoną rolą (`member` lub `editor`).
     * Jeśli użytkownik wcześniej odrzucił zaproszenie (`status = declined`), można je wysłać ponownie z parametrem `resend: true`.
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 1
     * @bodyParam user_id int required ID użytkownika. Example: 15
     * @bodyParam role string required member|editor. Example: member
     * @bodyParam resend boolean optional Ustaw na true, aby ponownie wysłać zaproszenie po odrzuceniu. Example: true
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

            if ($status === 'declined' && ($data['resend'] ?? false)) {
                $trip->members()->updateExistingPivot($userId, ['role' => $data['role'], 'status' => 'pending']);

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

            return response()->json(['message' => 'User already invited or a member'], 409);
        }

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
     * Zmiana roli uczestnika
     *
     * Zmienia rolę uczestnika podróży (tylko dla właściciela lub edytora).
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 1
     * @urlParam user int required ID użytkownika. Example: 10
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
     * Usunięcie uczestnika z podróży
     *
     * Usuwa uczestnika z podróży. Dostępne dla właściciela i edytora:
     * - Właściciel może usunąć dowolnego uczestnika.
     * - Edytor może usunąć tylko uczestnika o roli "member".
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 1
     * @urlParam user int required ID użytkownika do usunięcia. Example: 15
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
     * Akceptacja zaproszenia do podróży
     *
     * Użytkownik akceptuje swoje zaproszenie, jeśli status = pending.
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 1
     *
     * @response 200 {"message":"Invite accepted","trip_id":1,"user_id":15,"status":"accepted"}
     * @response 403 {"message":"No pending invite"}
     * @response 404 {"message":"Invite not found"}
     */
    public function accept(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

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
     * Odrzucenie zaproszenia do podróży
     *
     * Użytkownik odrzuca zaproszenie, jeśli status = pending.
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 1
     *
     * @response 200 {"message":"Invite declined","trip_id":1,"user_id":15,"status":"declined"}
     * @response 403 {"message":"No pending invite"}
     * @response 404 {"message":"Invite not found"}
     */
    public function decline(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

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
}
