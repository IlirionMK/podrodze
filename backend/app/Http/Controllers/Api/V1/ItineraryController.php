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
     * @authenticated
     * @operationId generateSimpleItinerary
     *
     * Generate a simple one-day recommended itinerary (cached).
     *
     * @urlParam trip_id integer required The ID of the trip. Example: 12
     *
     * @response 200 {
     *   "data": {
     *     "trip_id": 12,
     *     "day_count": 1,
     *     "schedule": [
     *       {
     *         "day": 1,
     *         "places": [
     *           {
     *             "id": 44,
     *             "name": "Whiskey in the Jar",
     *             "category_slug": "bar",
     *             "score": 4.5,
     *             "distance_m": 1200
     *           }
     *         ]
     *       }
     *     ],
     *     "cache_info": {
     *       "cached": false,
     *       "expires_in": 7200
     *     }
     *   }
     * }
     *
     * @response 400 {
     *   "error": "Unable to generate itinerary"
     * }
     */
    public function generate(Trip $trip_id): JsonResponse
    {
        $this->authorize('view', $trip_id);

        try {
            $dto = $this->itineraryService->generate($trip_id);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return (new ItineraryResource($dto))->response();
    }

    /**
     * @group Itinerary
     * @authenticated
     * @operationId generateFullItinerary
     *
     * Generate full multi-day itinerary using preferences, votes and nearby places.
     *
     * @urlParam trip_id integer required The ID of the trip. Example: 12
     *
     * @bodyParam days integer required Must be between 1 and 30. Example: 3
     * @bodyParam radius integer Radius in meters (100–20000). Example: 2000
     *
     * @response 200 {
     *   "data": {
     *     "trip_id": 12,
     *     "day_count": 3,
     *     "schedule": [
     *       {
     *         "day": 1,
     *         "places": ["..."]
     *       }
     *     ],
     *     "cache_info": {
     *       "cached": false,
     *       "expires_in": 7200
     *     }
     *   }
     * }
     *
     * @response 400 {
     *   "error": "Unable to generate itinerary"
     * }
     */
    public function generateFullRoute(Request $request, Trip $trip_id): JsonResponse
    {
        $this->authorize('view', $trip_id);

        $validated = $request->validate([
            'days'   => ['required', 'integer', 'min:1', 'max:30'],
            'radius' => ['nullable', 'integer', 'min:100', 'max:20000'],
        ]);

        $days   = (int) $validated['days'];
        $radius = (int) ($validated['radius'] ?? 2000);

        try {
            $dto = $this->itineraryService->generateFullRoute($trip_id, $days, $radius);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return (new ItineraryResource($dto))->response();
    }

    /**
     * @group Itinerary
     * @authenticated
     * @operationId aggregateTripPreferences
     *
     * Aggregate group preferences for trip members.
     *
     * @urlParam trip_id integer required The ID of the trip. Example: 4
     *
     * @response 200 {
     *   "data": {
     *     "museum": 1.5,
     *     "food": 2.0,
     *     "nature": 0.7,
     *     "nightlife": 1.3
     *   },
     *   "message": "Aggregated preferences calculated."
     * }
     */
    public function aggregatePreferences(Trip $trip_id): JsonResponse
    {
        $this->authorize('view', $trip_id);

        $prefs = $this->itineraryService->aggregatePreferences($trip_id);

        return response()->json([
            'data'    => $prefs,
            'message' => 'Aggregated preferences calculated.',
        ]);
    }
}
