<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Place extends Model
{
    protected $fillable = [
        'name',
        'google_place_id',
        'category_slug',
        'rating',
        'meta',
        'location',
    ];

    protected $casts = [
        'meta' => 'array',
        'rating' => 'float',
    ];

    protected static function booted()
    {
        static::saved(fn($place) => self::clearNearbyTripCaches($place));
        static::deleted(fn($place) => self::clearNearbyTripCaches($place));
    }

    protected static function clearNearbyTripCaches(Place $place): void
    {
        $coords = DB::selectOne("
            SELECT ST_X(location::geometry) AS lon, ST_Y(location::geometry) AS lat
            FROM places WHERE id = ?
        ", [$place->id]);

        if (!$coords) {
            foreach (Trip::all('id') as $trip) {
                Cache::forget("places:trip:{$trip->id}");
                Cache::forget("itinerary:trip:{$trip->id}");
            }
            return;
        }

        $trips = DB::select("
            SELECT id, start_latitude, start_longitude
            FROM trips
            WHERE start_latitude IS NOT NULL
              AND start_longitude IS NOT NULL
        ");

        foreach ($trips as $trip) {
            $distance = DB::selectOne("
                SELECT ST_Distance(
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) AS dist
            ", [$trip->start_longitude, $trip->start_latitude, $coords->lon, $coords->lat]);

            if ($distance && $distance->dist < 10000) {
                Cache::forget("places:trip:{$trip->id}");
                Cache::forget("itinerary:trip:{$trip->id}");
            }
        }
    }

    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'trip_place')
            ->withPivot(['order_index', 'status', 'note'])
            ->withTimestamps();
    }

    /**
     * Scope: find nearby places using PostGIS.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $lat
     * @param float $lon
     * @param int $radius Distance in meters (default: 2000)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNear($query, float $lat, float $lon, int $radius = 2000)
    {
        return $query->select([
            'places.*',
            DB::raw("ST_Distance(location::geography, ST_SetSRID(ST_MakePoint($lon, $lat), 4326)::geography) AS distance_m")
        ])
            ->whereRaw("ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)", [
                $lon, $lat, $radius
            ])
            ->orderBy('distance_m');
    }
}
