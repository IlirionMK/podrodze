<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TripUserController extends Controller
{
    use AuthorizesRequests;

    /**
     * @group Members
     *
     * Lista uczestników podróży
     *
     * Zwraca użytkowników przypisanych do podróży wraz z polem pivot: role i status.
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 2
     *
     * @response 200 [{
     *   "id": 2,
     *   "name": "User2",
     *   "email": "user2@example.com",
     *   "pivot": {
     *     "trip_id": 2,
     *     "user_id": 2,
     *     "role": "member",
     *     "status": "pending"
     *   }
     * }]
     */
    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

        $members = $trip->members()
            ->withPivot(['role', 'status'])
            ->get(['users.id', 'users.name', 'users.email']);

        return response()->json($members);
    }

    /**
     * @group Members
     *
     * Zmiana roli uczestnika
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 2
     * @urlParam user int required ID użytkownika. Example: 5
     * @bodyParam role string required member|editor. Example: editor
     *
     * @response 200 {"message":"Role updated"}
     */
    public function update(Request $request, Trip $trip, User $user)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'role' => ['required', 'in:member,editor'],
        ]);

        $trip->members()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return response()->json(['message' => 'Role updated']);
    }

    /**
     * @group Members
     *
     * Usunięcie uczestnika z podróży
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 2
     * @urlParam user int required ID użytkownika. Example: 5
     *
     * @response 204
     */
    public function destroy(Request $request, Trip $trip, User $user)
    {
        $this->authorize('update', $trip);

        $trip->members()->detach($user->id);

        return response()->noContent();
    }

    /**
     * @group Members / Invites
     *
     * Akceptacja zaproszenia do podróży
     *
     * Akceptuje zaproszenie zalogowanego użytkownika, jeśli status na pivocie = pending.
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 2
     *
     * @response 200 {"message":"Invite accepted","trip_id":2,"user_id":5,"status":"accepted"}
     * @response 403 {"message":"No pending invite"}
     * @response 404 {"message":"Invite not found"}
     */
    public function accept(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

        $user = $request->user();

        $pivot = $trip->members()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if (!$pivot) {
            return response()->json(['message' => 'Invite not found'], 404);
        }

        if (($pivot->status ?? null) !== 'pending') {
            return response()->json(['message' => 'No pending invite'], 403);
        }

        $trip->members()->updateExistingPivot($user->id, ['status' => 'accepted']);

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
     * Odrzuca zaproszenie zalogowanego użytkownika, jeśli status na pivocie = pending.
     *
     * @authenticated
     * @urlParam trip int required ID podróży. Example: 2
     *
     * @response 200 {"message":"Invite declined","trip_id":2,"user_id":5,"status":"declined"}
     * @response 403 {"message":"No pending invite"}
     * @response 404 {"message":"Invite not found"}
     */
    public function decline(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

        $user = $request->user();

        $pivot = $trip->members()
            ->where('users.id', $user->id)
            ->first()?->pivot;

        if (!$pivot) {
            return response()->json(['message' => 'Invite not found'], 404);
        }

        if (($pivot->status ?? null) !== 'pending') {
            return response()->json(['message' => 'No pending invite'], 403);
        }

        $trip->members()->updateExistingPivot($user->id, ['status' => 'declined']);

        return response()->json([
            'message' => 'Invite declined',
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'status'  => 'declined',
        ]);
    }
}
