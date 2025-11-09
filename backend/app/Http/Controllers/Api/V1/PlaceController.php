<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Interfaces\PlaceInterface;

class PlaceController extends Controller
{
    public function __construct(
        protected PlaceInterface $placeService
    ) {}

    /**
     * @group Places
     *
     * Find nearby places based on coordinates.
     *
     * @queryParam lat float required Latitude of the point. Example: 51.1079
     * @queryParam lon float required Longitude of the point. Example: 17.0385
     * @queryParam radius integer optional Search radius in meters. Default: 2000
     *
     * @response 200 scenario="Success" {
     *   "data": [
     *     {"id": 1, "name": "Panorama Sky Bar", "category_slug": "nightlife", "rating": 4.6, "distance_m": 512.4},
     *     {"id": 2, "name": "Muzeum Narodowe", "category_slug": "museum", "rating": 4.8, "distance_m": 893.1}
     *   ]
     * }
     */
    public function nearby(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:10', 'max:50000'],
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $radius = (int) ($validated['radius'] ?? 2000);

        $places = $this->placeService->findNearby($lat, $lon, $radius);

        return response()->json([
            'data' => $places,
        ]);
    }
}
