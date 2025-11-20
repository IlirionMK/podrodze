<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Interfaces\TripInterface;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use DomainException;

class TripController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TripInterface $tripService
    ) {}

    /**
     * @group Trips
     * @authenticated
     * @operationId listTrips
     *
     * List trips accessible for the current user.
     *
     * @response 200 {
     *   "data": [...]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $trips = $this->tripService->list($request->user());

        return TripResource::collection($trips)->response();
    }

    /**
     * @group Trips
     * @authenticated
     * @operationId createTrip
     *
     * Create a new trip.
     *
     * @bodyParam name string required Trip name. Example: "Weekend in Wrocław"
     * @bodyParam start_date string nullable Trip start date. Example: "2025-11-29"
     * @bodyParam end_date string nullable Trip end date. Example: "2025-12-02"
     *
     * @response 201 {
     *   "message": "Trip created successfully",
     *   "data": {...}
     * }
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = $this->tripService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Trip created successfully',
            'data'    => (new TripResource($trip))->resolve(),
        ], 201);
    }

    /**
     * @group Trips
     * @authenticated
     * @operationId getTrip
     *
     * Get a single trip by ID.
     *
     * @urlParam trip_id integer required The ID of the trip. Example: 5
     *
     * @response 200 {
     *   "data": {...}
     * }
     */
    public function show(Trip $trip_id): JsonResponse
    {
        $this->authorize('view', $trip_id);

        $trip_id->load(['owner', 'members']);

        return response()->json([
            'data' => (new TripResource($trip_id))->resolve(),
        ]);
    }

    /**
     * @group Trips
     * @authenticated
     * @operationId updateTrip
     *
     * Update an existing trip.
     *
     * @urlParam trip_id integer required The ID of the trip. Example: 5
     *
     * @bodyParam name string Trip name. Example: "Updated trip name"
     * @bodyParam start_date string nullable Example: "2025-11-30"
     * @bodyParam end_date string nullable Example: "2025-12-21"
     *
     * @response 200 {
     *   "message": "Trip updated successfully",
     *   "data": {...}
     * }
     *
     * @response 400 {
     *   "error": "Invalid update payload"
     * }
     */
    public function update(UpdateTripRequest $request, Trip $trip_id): JsonResponse
    {
        $this->authorize('update', $trip_id);

        try {
            $updated = $this->tripService->update($request->validated(), $trip_id);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Trip updated successfully',
            'data'    => (new TripResource($updated))->resolve(),
        ]);
    }

    /**
     * @group Trips
     * @authenticated
     * @operationId deleteTrip
     *
     * Delete a trip.
     *
     * @urlParam trip_id integer required The ID of the trip. Example: 5
     *
     * @response 200 { "message": "Trip deleted successfully" }
     */
    public function destroy(Trip $trip_id): JsonResponse
    {
        $this->authorize('delete', $trip_id);

        $this->tripService->delete($trip_id);

        return response()->json([
            'message' => 'Trip deleted successfully'
        ]);
    }

    /**
     * @group Trips
     * @authenticated
     * @operationId updateTripStartLocation
     *
     * Update the starting location of the trip.
     *
     * @urlParam trip_id integer required The ID of the trip. Example: 5
     *
     * @bodyParam start_latitude number required Latitude. Example: 51.21
     * @bodyParam start_longitude number required Longitude. Example: 16.16
     *
     * @response 200 {
     *   "message": "Start location updated",
     *   "data": {...}
     * }
     *
     * @response 400 { "error": "Invalid coordinates" }
     */
    public function updateStartLocation(Request $request, Trip $trip_id): JsonResponse
    {
        $this->authorize('update', $trip_id);

        $validated = $request->validate([
            'start_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'start_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $updated = $this->tripService->updateStartLocation($validated, $trip_id);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Start location updated',
            'data'    => (new TripResource($updated))->resolve(),
        ]);
    }
}
