<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TripPlaceStoreRequest;
use App\Http\Requests\TripPlaceUpdateRequest;
use App\Http\Resources\TripPlaceResource;
use App\Http\Resources\TripVoteResource;
use App\Interfaces\PlaceInterface;
use App\Models\Trip;
use App\Models\Place;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

class TripPlaceController extends Controller
{
    public function __construct(
        protected PlaceInterface $placeService
    ) {}

    public function index(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $places = $this->placeService->listForTrip($trip);

        return response()->json([
            'data' => TripPlaceResource::collection($places),
        ]);
    }

    public function store(TripPlaceStoreRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $dto = $this->placeService->addToTrip($trip, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Place added to trip',
            'data'    => new TripPlaceResource($dto),
        ], 201);
    }

    public function update(TripPlaceUpdateRequest $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $dto = $this->placeService->updateTripPlace($trip, $place, $request->validated());
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'message' => 'Trip place updated',
            'data'    => new TripPlaceResource($dto),
        ]);
    }

    public function destroy(Request $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $this->placeService->detachFromTrip($trip, $place, $request->user());
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'message' => 'Place removed from trip',
        ]);
    }

    public function vote(TripPlaceUpdateRequest $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        try {
            $vote = $this->placeService->saveTripVote(
                $trip,
                $place,
                $request->user(),
                (int) $validated['score']
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Vote saved',
            'data'    => new TripVoteResource($vote),
        ]);
    }
}
