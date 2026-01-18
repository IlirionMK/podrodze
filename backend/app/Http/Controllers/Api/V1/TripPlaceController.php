<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TripPlaceStoreRequest;
use App\Http\Requests\TripPlaceUpdateRequest;
use App\Http\Requests\TripPlaceVoteRequest;
use App\Http\Resources\TripPlaceResource;
use App\Http\Resources\TripPlaceVoteSummaryResource;
use App\Http\Resources\TripVoteResource;
use App\Interfaces\PlaceInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Services\External\GooglePlacesService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Google Places Search (Nearby) based on Trip Location
     * URL: /trips/{trip}/places/nearby
     */
    public function nearbyGoogle(Request $request, Trip $trip, GooglePlacesService $googleService): JsonResponse
    {
        $this->authorize('view', $trip);

        if (!$trip->start_latitude || !$trip->start_longitude) {
            return response()->json([
                'message' => 'Trip has no starting location defined.',
                'data'    => [],
            ], 400);
        }

        $radius = (int) $request->input('radius', 1500);
        $limit  = (int) $request->input('limit', 20);

        $places = $googleService->fetchNearbyForTrip($trip, $radius, $limit);

        return response()->json([
            'data' => $places,
        ]);
    }

    public function votes(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $items = $this->placeService->listTripVotes($trip, $request->user());

        return response()->json([
            'data' => TripPlaceVoteSummaryResource::collection($items),
        ]);
    }

    public function store(TripPlaceStoreRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $dto = $this->placeService->addToTrip($trip, $request->validated(), $request->user());
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            $message = $e->getMessage();

            if ($message === 'This place is already attached to the trip.') {
                return response()->json(['message' => $message], 409);
            }

            return response()->json(['message' => $message], 400);
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

    public function vote(TripPlaceVoteRequest $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('vote', $trip);

        $score = (int) $request->validated()['score'];

        try {
            $vote = $this->placeService->saveTripVote(
                $trip,
                $place,
                $request->user(),
                $score
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
