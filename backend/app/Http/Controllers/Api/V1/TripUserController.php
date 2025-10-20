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
     * @authenticated
     * @urlParam trip int required ID podróży.
     *
     * @response 200 [
     *   {
     *     "id": 2,
     *     "name": "User2",
     *     "email": "user2@example.com",
     *     "pivot": {
     *       "trip_id": 2,
     *       "user_id": 2,
     *       "role": "member"
     *     }
     *   }
     * ]
     */

    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

        $members = $trip->members()
            ->withPivot('role')
            ->get(['users.id', 'users.name', 'users.email']);

        return response()->json($members);
    }

    public function update(Request $request, Trip $trip, User $user)
    {
        $this->authorize('update', $trip);
        $data = $request->validate(['role' => ['required', 'in:member,editor']]);
        $trip->members()->updateExistingPivot($user->id, ['role' => $data['role']]);
        return response()->json(['message' => 'Role updated']);
    }

    public function destroy(Request $request, Trip $trip, User $user)
    {
        $this->authorize('update', $trip);
        $trip->members()->detach($user->id);
        return response()->noContent();
    }
}
