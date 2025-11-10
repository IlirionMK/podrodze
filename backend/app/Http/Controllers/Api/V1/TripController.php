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

    /** Get trips where the user is an owner or a member. */
    public function index(Request $request)
    {
        $trips = $this->tripService->list($request);
        return TripResource::collection($trips);
    }

    /** Create a new trip. */
    public function store(Request $request)
    {
        $trip = $this->tripService->create($request);
        return (new TripResource($trip))
            ->response()
            ->setStatusCode(201);
    }

    /** Get details of a specific trip. */
    public function show(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $trip->load(['members', 'owner']);
        return new TripResource($trip);
    }

    /** Update trip details. */
    public function update(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);
        $updatedTrip = $this->tripService->update($request, $trip);
        return new TripResource($updatedTrip);
    }

    /** Delete a trip. */
    public function destroy(Request $request, Trip $trip)
    {
        $this->authorize('delete', $trip);
        $this->tripService->delete($trip);
        return response()->noContent();
    }

    /** Invite user to a trip. */
    public function invite(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);
        $result = $this->tripService->inviteUser(
            $trip,
            $request->user(),
            $request->all()
        );
        return response()->json($result['body'], $result['status']);
    }

    /** Set or update the start location of a trip. */
    public function updateStartLocation(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);
        $result = $this->tripService->updateStartLocation($request, $trip);
        return response()->json($result);
    }
}
