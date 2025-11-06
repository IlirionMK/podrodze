<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\PlaceService;

class PlaceController extends Controller
{
    public function __construct(protected PlaceService $placeService) {}

    /**
     * @group Places
     *
     * Find nearby places based on coordinates.
     *
     * @queryParam lat float required Latitude of the point. Example: 51.1079
     * @queryParam lon float required Longitude of the point. Example: 17.0385
     * @queryParam radius integer optional Search radius in meters. Default: 2000
     * @response 200 scenario="Success" {
     *   "data": [
     *     {"id": 1, "name": "Panorama Sky Bar", "distance_m": 512.4},
     *     {"id": 2, "name": "Muzeum Narodowe", "distance_m": 893.1}
     *   ]
     * }
     */
    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'radius' => 'nullable|integer|min:10|max:50000',
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $radius = (int) ($validated['radius'] ?? 2000);

        $places = $this->placeService->findNearby($lat, $lon, $radius);

        return response()->json(['data' => $places], Response::HTTP_OK);
    }
}
