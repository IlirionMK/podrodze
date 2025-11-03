<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class PlaceController extends Controller
{
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

        $lat = $validated['lat'];
        $lon = $validated['lon'];
        $radius = $validated['radius'] ?? 2000;

        $places = DB::select("
            SELECT id, name, category_slug, rating,
                   ST_Distance(
                       location,
                       ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography
                   ) AS distance_m
            FROM places
            WHERE ST_DWithin(
                location,
                ST_SetSRID(ST_MakePoint(:lon2, :lat2), 4326)::geography,
                :radius
            )
            ORDER BY distance_m ASC
            LIMIT 50
        ", [
            'lat' => $lat,
            'lon' => $lon,
            'lat2' => $lat,
            'lon2' => $lon,
            'radius' => $radius,
        ]);

        return response()->json([
            'data' => $places,
        ], Response::HTTP_OK);
    }
}
