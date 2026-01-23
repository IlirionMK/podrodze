<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItineraryResource;
use App\Interfaces\ItineraryServiceInterface;
use App\Models\Trip;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ItineraryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ItineraryServiceInterface $itineraryService
    ) {}

    public function generate(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        try {
            $dto = $this->itineraryService->generate($trip);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return (new ItineraryResource($dto))->response();
    }

    public function generateFullRoute(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'days'   => ['required', 'integer', 'min:1', 'max:30'],
            'radius' => ['nullable', 'integer', 'min:100', 'max:20000'],
        ]);

        try {
            $dto = $this->itineraryService->generateFullRoute(
                $trip,
                (int) $validated['days'],
                (int) ($validated['radius'] ?? 2000)
            );
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $saved = $this->itineraryService->getSaved($trip);

        return response()->json([
            'data' => (new ItineraryResource($dto))->resolve(),
            'meta' => [
                'updated_at' => $saved['updated_at'] ?? null,
            ],
        ]);
    }

    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $saved = $this->itineraryService->getSaved($trip);

        if (!$saved) {
            return response()->json([
                'data' => null,
                'meta' => ['updated_at' => null],
            ]);
        }

        return response()->json([
            'data' => (new ItineraryResource($saved['dto']))->resolve(),
            'meta' => ['updated_at' => $saved['updated_at']],
        ]);
    }

    public function update(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'day_count' => ['required', 'integer', 'min:1', 'max:30'],
            'schedule' => ['required', 'array'],
            'schedule.*.day' => ['required', 'integer', 'min:1', 'max:30'],
            'schedule.*.places' => ['required', 'array'],
            'schedule.*.places.*.id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date'],
        ]);

        try {
            $res = $this->itineraryService->updateSaved(
                $trip,
                (int) $validated['day_count'],
                $validated['schedule'],
                (string) $validated['expected_updated_at']
            );
        } catch (DomainException $e) {
            $msg = (string) $e->getMessage();
            if (str_starts_with($msg, 'itinerary_conflict:')) {
                $current = trim(substr($msg, strlen('itinerary_conflict:')));
                return response()->json([
                    'error' => 'itinerary_conflict',
                    'message' => 'Itinerary was updated by another user.',
                    'meta' => ['updated_at' => $current !== '' ? $current : null],
                ], 409);
            }

            return response()->json(['error' => $msg], 400);
        }

        return response()->json([
            'data' => (new ItineraryResource($res['dto']))->resolve(),
            'meta' => ['updated_at' => $res['updated_at']],
        ]);
    }

    public function aggregatePreferences(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $prefs = $this->itineraryService->aggregatePreferences($trip);

        return response()->json([
            'data'    => $prefs,
            'message' => 'Aggregated preferences calculated.',
        ]);
    }
}
