<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItineraryResource;
use App\Interfaces\ItineraryServiceInterface;
use App\Models\Trip;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

class ItineraryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ItineraryServiceInterface $itineraryService
    ) {}

    /**
     * @group Itinerary
     * Generate a simple one-day recommended itinerary (cached).
     */
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

    /**
     * @group Itinerary
     * Generate full multi-day itinerary using preferences, votes and nearby places.
     */
    public function generateFullRoute(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'days'   => ['required', 'integer', 'min:1', 'max:30'],
            'radius' => ['nullable', 'integer', 'min:100', 'max:20000'],
        ]);

        $days   = (int) $validated['days'];
        $radius = (int) ($validated['radius'] ?? 2000);

        try {
            $dto = $this->itineraryService->generateFullRoute($trip, $days, $radius);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return (new ItineraryResource($dto))->response();
    }

    /**
     * @group Itinerary
     * Aggregate group preferences for trip members.
     */
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
