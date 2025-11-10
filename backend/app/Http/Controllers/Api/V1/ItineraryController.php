<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItineraryResource;
use App\Interfaces\ItineraryServiceInterface;
use App\Interfaces\PreferenceAggregatorServiceInterface;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ItineraryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ItineraryServiceInterface $itineraryService,
        protected PreferenceAggregatorServiceInterface $aggregator
    ) {}
    /**
     * @group Trips / Itinerary
     *
     * Get aggregated group preferences.
     */
    public function aggregatePreferences(Trip $trip)
    {
        $this->authorize('view', $trip);

        return response()->json([
            'trip_id' => $trip->id,
            'group_preferences' => $this->aggregator->getGroupPreferences($trip),
        ]);
    }

    /**
     * @group Trips / Itinerary
     *
     * Generate a recommended itinerary (cached).
     */
    public function generate(Trip $trip)
    {
        $this->authorize('view', $trip);

        $dto = $this->itineraryService->generate($trip);
        return new ItineraryResource($dto);
    }

    /**
     * @group Trips / Itinerary
     *
     * Build full itinerary based on cached places.
     */
    public function full(Trip $trip)
    {
        $this->authorize('view', $trip);

        $dto = $this->itineraryService->buildFull($trip);
        return new ItineraryResource($dto);
    }

    /**
     * @group Trips / Itinerary
     *
     * Generate and persist full route (multi-day schedule).
     */
    public function generateFullRoute(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

        $days = (int) $request->input('days', 2);
        $radius = (int) $request->input('radius', 2000);

        $dto = $this->itineraryService->generateFullRoute($trip, $days, $radius);
        return new ItineraryResource($dto);
    }
}
