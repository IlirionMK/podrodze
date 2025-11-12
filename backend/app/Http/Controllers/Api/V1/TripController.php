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

class TripController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected TripInterface $tripService) {}

    /**
     * @group Trips
     * Get all trips of the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $trips = $this->tripService->list($request->user());

        return response()->json([
            'data' => TripResource::collection($trips),
        ]);
    }

    /**
     * @group Trips
     * Create a new trip.
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = $this->tripService->create($request->validated(), $request->user());

        return response()->json([
            'data' => new TripResource($trip),
            'message' => 'Trip created successfully.',
        ], 201);
    }

    /**
     * @group Trips
     * Get details of a specific trip.
     */
    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        $trip->load(['members', 'owner']);

        return response()->json([
            'data' => new TripResource($trip),
        ]);
    }

    /**
     * @group Trips
     * Update a trip.
     */
    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $updatedTrip = $this->tripService->update($request->validated(), $trip);

        return response()->json([
            'data' => new TripResource($updatedTrip),
            'message' => 'Trip updated successfully.',
        ]);
    }

    /**
     * @group Trips
     * Delete a trip.
     */
    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);
        $this->tripService->delete($trip);

        return response()->noContent();
    }

    /**
     * @group Trips
     * Update trip start location (latitude, longitude).
     */
    public function updateStartLocation(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'start_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'start_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $updated = $this->tripService->updateStartLocation($validated, $trip);

        return response()->json([
            'data' => new TripResource($updated),
            'message' => 'Start location updated.',
        ]);
    }
}
