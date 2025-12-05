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
     * @bodyParam start_date string nullable Example: "2025-11-29"
     * @bodyParam end_date string nullable Example: "2025-12-02"
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
     * @urlParam trip integer required The ID of the trip. Example: 10
     *
     * @response 200 {
     *   "data": {...}
     * }
     */
    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $trip->load(['owner', 'members']);

        return response()->json([
            'data' => (new TripResource($trip))->resolve(),
        ]);
    }

    /**
     * @group Trips
     * @authenticated
     * @operationId updateTrip
     *
     * Update an existing trip.
     *
     * @urlParam trip integer required The ID of the trip. Example: 10
     *
     * @bodyParam name string Example: "Updated trip name"
     * @bodyParam start_date string nullable Example: "2025-11-30"
     * @bodyParam end_date string nullable Example: "2025-12-21"
     *
     * @response 200 {
     *   "message": "Trip updated successfully",
     *   "data": {...}
     * }
     */
    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $updated = $this->tripService->update($request->validated(), $trip);
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
     * @urlParam trip integer required The ID of the trip. Example: 10
     *
     * @response 200 { "message": "Trip deleted successfully" }
     */
    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);

        $this->tripService->delete($trip);

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
     * @urlParam trip integer required Example: 10
     *
     * @bodyParam start_latitude number required Example: 51.21
     * @bodyParam start_longitude number required Example: 16.16
     *
     * @response 200 {
     *   "message": "Start location updated",
     *   "data": {...}
     * }
     */
    public function updateStartLocation(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'start_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'start_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $updated = $this->tripService->updateStartLocation($validated, $trip);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Start location updated',
            'data'    => (new TripResource($updated))->resolve(),
        ]);
    }
}
