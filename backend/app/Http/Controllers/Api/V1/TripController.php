<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TripResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\Trip;
use App\Interfaces\TripInterface;
use Illuminate\Http\JsonResponse;

class TripController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TripInterface $tripService
    ) {}

    /**
     * @group Trips
     *
     * Get trips where the user is an owner or a member.
     *
     * @authenticated
     * @response 200 scenario="Example" {
     *   "data": [
     *     {"id": 1, "name": "Weekend in Kraków", "owner_id": 5},
     *     {"id": 2, "name": "Roadtrip Baltic Sea", "owner_id": 5}
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $trips = $this->tripService->list($request);

        // Пагинация через Resource Collection
        return response()->json(TripResource::collection($trips));
    }

    /**
     * @group Trips
     *
     * Create a new trip.
     *
     * @authenticated
     * @bodyParam name string required Example: "Weekend in Kraków"
     * @bodyParam start_date date optional Example: "2025-04-10"
     * @bodyParam end_date date optional Example: "2025-04-12"
     *
     * @response 201 scenario="Example" {
     *   "id": 1,
     *   "name": "Weekend in Kraków",
     *   "owner_id": 5
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $trip = $this->tripService->create($request);

        return response()->json(new TripResource($trip), 201);
    }

    /**
     * @group Trips
     *
     * Get details of a specific trip.
     *
     * @authenticated
     * @urlParam trip integer required Example: 1
     */
    public function show(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $trip->load(['members', 'owner']);

        return response()->json(new TripResource($trip));
    }

    /**
     * @group Trips
     *
     * Update trip details.
     *
     * @authenticated
     * @urlParam trip integer required Example: 1
     * @bodyParam name string optional Example: "Updated name"
     * @bodyParam start_date date optional Example: "2025-04-11"
     * @bodyParam end_date date optional Example: "2025-04-13"
     */
    public function update(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $updatedTrip = $this->tripService->update($request, $trip);

        return response()->json(new TripResource($updatedTrip));
    }

    /**
     * @group Trips
     *
     * Delete a trip.
     *
     * @authenticated
     * @urlParam trip integer required Example: 1
     * @response 204
     */
    public function destroy(Request $request, Trip $trip): \Illuminate\Http\Response
    {
        $this->authorize('delete', $trip);
        $this->tripService->delete($trip);

        return response()->noContent();
    }

    /**
     * @group Trips / Members
     *
     * Invite user to a trip.
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
     * @response 400 {"message":"Owner is already a member"}
     */
    public function invite(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $result = $this->tripService->invite($request, $trip);

        return response()->json($result['body'], $result['status']);
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
    public function updateStartLocation(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $result = $this->tripService->updateStartLocation($request, $trip);

        return response()->json($result);
    }
}
