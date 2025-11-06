<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PlaceService
{
    /**
     * Find nearby places using PostGIS.
     *
     * @param float $lat
     * @param float $lon
     * @param int $radius
     * @param int $limit
     * @return array
     */
    public function findNearby(float $lat, float $lon, int $radius = 2000, int $limit = 50): array
    {
        return DB::select("
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
            LIMIT :limit
        ", [
            'lat' => $lat,
            'lon' => $lon,
            'lat2' => $lat,
            'lon2' => $lon,
            'radius' => $radius,
            'limit' => $limit,
        ]);
    }
}
