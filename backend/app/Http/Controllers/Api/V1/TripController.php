<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\Trip;

class TripController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();

        $trips = Trip::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', function ($query) use ($user) {
                $query->where('trip_user.user_id', $user->id);
            })
            ->with(['members:id,name,email'])
            ->latest()
            ->paginate(10);

        return response()->json($trips);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $trip = Trip::create([
            'name'       => $data['name'],
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date'] ?? null,
            'owner_id'   => $request->user()->id,
        ]);

        return response()->json($trip, 201);
    }

    public function show(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        return $trip;
    }

    public function update(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date'   => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $trip->update($data);

        return $trip->fresh();
    }

    public function destroy(Request $request, Trip $trip)
    {
        $this->authorize('delete', $trip);
        $trip->delete();

        return response()->noContent();
    }

    /**
     * @group Trips / Members
     *
     * Invite user to a trip
     *
     * Sends an invitation to a user with the given `user_id`.
     * Sets `status = pending`. Handles resending, duplicates and declined invitations.
     *
     * @authenticated
     *
     * @urlParam trip integer required Trip ID. Example: 2
     * @bodyParam user_id integer required ID of the invited user. Example: 5
     * @bodyParam role string optional member|editor. Default: member. Example: member
     *
     * @response 201 {"message":"User invited","status":"pending"}
     * @response 200 {"message":"Invite already pending"}
     * @response 200 {"message":"Already a member"}
     * @response 200 {"message":"Invite re-sent (pending)","status":"pending"}
     * @response 400 {"message":"Owner is already a member"}
     * @response 401 {"message":"Unauthenticated"}
     */
    public function invite(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['sometimes', 'string', 'in:member,editor'],
        ]);

        $userId = (int) $data['user_id'];
        $role   = $data['role'] ?? 'member';

        if ($trip->owner_id === $userId) {
            return response()->json(['message' => 'Owner is already a member'], 400);
        }

        $existing = $trip->members()
            ->withPivot(['role', 'status'])
            ->where('users.id', $userId)
            ->first();

        if ($existing) {
            $status = $existing->pivot->status ?? null;

            if ($status === 'accepted') {
                return response()->json(['message' => 'Already a member'], 200);
            }

            if ($status === 'pending') {
                return response()->json(['message' => 'Invite already pending'], 200);
            }

            if ($status === 'declined') {
                $trip->members()->updateExistingPivot($userId, [
                    'role'   => $role,
                    'status' => 'pending',
                ]);

                return response()->json([
                    'message' => 'Invite re-sent (pending)',
                    'status'  => 'pending',
                ], 200);
            }

            $trip->members()->updateExistingPivot($userId, [
                'role'   => $role,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Invite set to pending',
                'status'  => 'pending',
            ], 200);
        }

        $trip->members()->syncWithoutDetaching([
            $userId => ['role' => $role, 'status' => 'pending'],
        ]);

        return response()->json([
            'message' => 'User invited',
            'status'  => 'pending',
        ], 201);
    }
    /**
     * @group Trips
     *
     * Set or update the start location of a trip.
     *
     * Allows the trip owner (or editor) to define the starting point
     * used later for itinerary generation and nearby place searches.
     *
     * @authenticated
     * @urlParam trip int required Trip ID. Example: 1
     * @bodyParam start_latitude float required Example: 51.1079
     * @bodyParam start_longitude float required Example: 17.0385
     *
     * @response 200 scenario="Example" {
     *   "trip_id": 1,
     *   "start_latitude": 51.1079,
     *   "start_longitude": 17.0385,
     *   "message": "Start location updated successfully."
     * }
     */
    public function updateStartLocation(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'start_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'start_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $trip->update($data);

        return response()->json([
            'trip_id'        => $trip->id,
            'start_latitude' => $trip->start_latitude,
            'start_longitude'=> $trip->start_longitude,
            'message'        => 'Start location updated successfully.',
        ]);
    }

}
