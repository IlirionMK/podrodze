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

    public function __construct(protected TripInterface $tripService) {}

    public function index(Request $request): JsonResponse
    {
        $trips = $this->tripService->list($request->user());
        return TripResource::collection($trips)->response();
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = $this->tripService->create($request->validated(), $request->user());

        return response()->json([
            'data' => new TripResource($trip),
            'message' => 'Trip created successfully',
        ], 201);
    }

    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $trip->load(['owner', 'members']);

        return response()->json([
            'data' => new TripResource($trip),
        ]);
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $updatedTrip = $this->tripService->update($request->validated(), $trip);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'data' => new TripResource($updatedTrip),
            'message' => 'Trip updated successfully',
        ]);
    }

    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);

        $this->tripService->delete($trip);

        return response()->json([
            'message' => 'Trip deleted successfully'
        ], 200);
    }

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
            'data' => new TripResource($updated),
            'message' => 'Start location updated',
        ]);
    }
}
