<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TripPlaceResource;
use App\Http\Resources\TripVoteResource;
use App\Interfaces\PlaceInterface;
use App\Models\Trip;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TripPlaceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PlaceInterface $placeService
    ) {}

    /**
     * @group Trips / Places
     *
     * Get all places attached to a trip.
     */
    public function index(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $places = $this->placeService->listForTrip($trip);

        return response()->json([
            'data' => TripPlaceResource::collection($places),
        ]);
    }

    /**
     * @group Trips / Places
     *
     * Attach a place to a trip.
     */
    public function store(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'place_id'    => 'required|exists:places,id',
            'status'      => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed'    => 'nullable|boolean',
            'day'         => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note'        => 'nullable|string|max:255',
        ]);

        try {
            $dto = $this->placeService->attachToTrip($trip, $validated, $request->user());

            return (new TripPlaceResource($dto))
                ->additional(['message' => 'Place added to trip'])
                ->response()
                ->setStatusCode(201);

        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group Trips / Places
     *
     * Update a place entry within a trip.
     */
    public function update(Request $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'status'      => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed'    => 'nullable|boolean',
            'day'         => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note'        => 'nullable|string|max:255',
        ]);

        try {
            $dto = $this->placeService->updateTripPlace($trip, $place, $validated);

            return (new TripPlaceResource($dto))
                ->additional(['message' => 'Trip place updated'])
                ->response();

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group Trips / Places
     *
     * Remove a place from a trip.
     */
    public function destroy(Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $this->placeService->detachFromTrip($trip, $place);

            return response()->json(['message' => 'Place removed from trip'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group Trips / Places
     *
     * Submit or update a vote for a place within a trip.
     */
    public function vote(Request $request, Trip $trip, Place $place): JsonResponse
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

        return (new TripVoteResource($vote))
            ->additional(['message' => 'Vote saved'])
            ->response();
    }
}
