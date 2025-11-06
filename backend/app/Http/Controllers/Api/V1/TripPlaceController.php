<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TripPlaceResource;
use App\Http\Resources\TripVoteResource;
use App\Models\Trip;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\TripPlaceService;
use App\Services\TripPlaceVoteService;

class TripPlaceController extends Controller
{
    public function __construct(
        protected TripPlaceService $tripPlaceService,
        protected TripPlaceVoteService $tripPlaceVoteService
    ) {}

    public function index(Trip $trip)
    {
        $places = $this->tripPlaceService->listAsDto($trip);

        return response()->json([
            'data' => TripPlaceResource::collection($places),
        ]);
    }

    public function store(Request $request, Trip $trip)
    {
        $validated = $request->validate([
            'place_id'    => 'required|exists:places,id',
            'status'      => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed'    => 'nullable|boolean',
            'day'         => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note'        => 'nullable|string|max:255',
        ]);

        if ($this->tripPlaceService->exists($trip, (int)$validated['place_id'])) {
            return response()->json([
                'message' => 'This place is already attached to the trip.',
            ], Response::HTTP_CONFLICT);
        }

        $this->tripPlaceService->attach($trip, (int)$validated['place_id'], [
            'status'      => $validated['status'] ?? 'planned',
            'is_fixed'    => $validated['is_fixed'] ?? false,
            'day'         => $validated['day'] ?? null,
            'order_index' => $validated['order_index'] ?? null,
            'note'        => $validated['note'] ?? null,
            'added_by'    => $request->user()->id ?? null,
        ]);

        return response()->json(['message' => 'Place added to trip'], Response::HTTP_CREATED);
    }

    public function update(Request $request, Trip $trip, Place $place)
    {
        $validated = $request->validate([
            'status'      => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed'    => 'nullable|boolean',
            'day'         => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note'        => 'nullable|string|max:255',
        ]);

        if (! $this->tripPlaceService->exists($trip, $place->id)) {
            return response()->json(['message' => 'Place not found in this trip.'], Response::HTTP_NOT_FOUND);
        }

        $this->tripPlaceService->update($trip, $place, array_filter($validated, fn($v) => !is_null($v)));

        return response()->json(['message' => 'Trip place updated']);
    }

    public function destroy(Trip $trip, Place $place)
    {
        if (! $this->tripPlaceService->exists($trip, $place->id)) {
            return response()->json(['message' => 'Place not found in this trip.'], Response::HTTP_NOT_FOUND);
        }

        $this->tripPlaceService->detach($trip, $place);

        return response()->json(['message' => 'Place removed from trip']);
    }

    public function vote(Request $request, Trip $trip, Place $place)
    {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $vote = $this->tripPlaceVoteService->saveVote(
            $trip,
            $place,
            $request->user()->id,
            (int)$validated['score']
        );

        return TripVoteResource::make($vote)
            ->additional(['message' => 'Vote saved']);
    }
}
