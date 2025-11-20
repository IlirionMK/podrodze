<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\PlacesSyncService;
use App\Interfaces\PlaceInterface;
use App\Http\Resources\PlaceResource;
use DomainException;

class PlaceController extends Controller
{
    public function __construct(
        protected PlacesSyncService $placesSync,
        protected PlaceInterface $placeService
    ) {}

    /**
     * @group Places
     * @authenticated
     * @operationId findNearbyPlaces
     *
     * Find nearby places using Google Places API (synchronizes) and PostGIS (distance filter).
     *
     * @queryParam lat number required Latitude between -90 and 90. Example: 51.21
     * @queryParam lon number required Longitude between -180 and 180. Example: 16.16
     * @queryParam radius integer The search radius in meters (10–50000). Example: 2000
     *
     * @response 200 {
     *   "message": "Nearby places synchronized successfully",
     *   "summary": {
     *       "added": 3,
     *       "updated": 17
     *   },
     *   "data": [
     *     {
     *       "id": 237,
     *       "google_place_id": "ChIJvRWwnIUTD0cRy2PMOWG2D3Q",
     *       "name": "Speakeasy Bar | Legnica",
     *       "category_slug": "bar",
     *       "rating": 5,
     *       "meta": {
     *         "icon": "...",
     *         "types": ["bar", "night_club"],
     *         "address": "Rycerska 2, Legnica"
     *       },
     *       "lat": 51.209053,
     *       "lon": 16.160364,
     *       "distance_m": 108.4
     *     }
     *   ]
     * }
     *
     * @response 400 {
     *   "error": "Google Places quota exceeded"
     * }
     */
    public function nearby(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat'    => ['required', 'numeric', 'between:-90,90'],
            'lon'    => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:10', 'max:50000'],
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $radius = (int) ($validated['radius'] ?? 2000);

        try {
            $summary = $this->placesSync->fetchAndStore($lat, $lon, $radius);
            $places = $this->placeService->findNearby($lat, $lon, $radius);
        } catch (DomainException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        return (PlaceResource::collection($places))
            ->additional([
                'message' => 'Nearby places synchronized successfully',
                'summary' => $summary,
            ])
            ->response();
    }
}
