<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Place;
use App\Models\TripItinerary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\DTO\Itinerary\Itinerary;
use App\DTO\Itinerary\ItineraryDay;
use App\DTO\Itinerary\ItineraryPlace;

class ItineraryService
{
    public function __construct(
        protected PreferenceAggregatorService $aggregator,
        protected PlacesSyncService $placesSync
    ) {}

    /**
     * Generate recommended itinerary (cached)
     */
    public function generate(Trip $trip): Itinerary
    {
        if (!$trip->start_latitude || !$trip->start_longitude) {
            return new Itinerary($trip->id, 0, [], ['error' => 'Trip has no start location set.']);
        }

        $cacheKey = "itinerary:trip:{$trip->id}";

        $places = Cache::remember($cacheKey, now()->addHours(6), function () use ($trip) {
            $prefs = $this->aggregator->getGroupPreferences($trip);
            if (empty($prefs)) {
                return [];
            }

            $centerLat = $trip->start_latitude;
            $centerLon = $trip->start_longitude;
            $radius = 2000;

            $places = Place::select([
                'places.id', 'places.name', 'places.category_slug', 'places.rating',
                DB::raw("ST_Distance(location::geography, ST_SetSRID(ST_MakePoint($centerLon, $centerLat), 4326)::geography) AS distance_m")
            ])
                ->whereRaw("ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)", [$centerLon, $centerLat, $radius])
                ->get();

            return $places->map(function ($place) use ($prefs) {
                $prefScore = $prefs[$place->category_slug] ?? 0.5;
                $place->score = round(
                    ($prefScore * 2.0) +
                    (($place->rating ?? 0) * 0.8) -
                    ($place->distance_m / 1500),
                    2
                );
                return $place;
            })->sortByDesc('score')->values()->take(10);
        });

        $dtoPlaces = collect($places)->map(fn($p) => new ItineraryPlace(
            $p->id,
            $p->name,
            $p->category_slug,
            $p->score ?? 0,
            round($p->distance_m ?? 0)
        ))->toArray();

        return new Itinerary(
            $trip->id,
            1,
            [new ItineraryDay(1, $dtoPlaces)],
            [
                'itinerary_ttl_h' => 6,
                'places_ttl_h' => 12,
            ]
        );
    }

    /**
     * Build full itinerary from cached places
     */
    public function buildFull(Trip $trip): Itinerary
    {
        $places = Cache::get("places:trip:{$trip->id}");
        if (!$places) {
            return new Itinerary($trip->id, 0, [], ['error' => 'No cached places for this trip']);
        }

        $clusters = [];
        foreach ($places as $place) {
            $grouped = false;
            foreach ($clusters as &$cluster) {
                if (abs($cluster['lat'] - $place['lat']) < 0.01 && abs($cluster['lon'] - $place['lon']) < 0.01) {
                    $cluster['places'][] = $place;
                    $grouped = true;
                    break;
                }
            }
            if (!$grouped) {
                $clusters[] = [
                    'lat' => $place['lat'],
                    'lon' => $place['lon'],
                    'places' => [$place],
                ];
            }
        }

        $schedule = [];
        $day = 1;
        foreach ($clusters as $cluster) {
            $schedule[] = new ItineraryDay($day++, $cluster['places']);
        }

        TripItinerary::updateOrCreate(
            ['trip_id' => $trip->id],
            [
                'schedule' => collect($schedule)->toArray(),
                'day_count' => count($schedule),
                'generated_at' => now(),
            ]
        );

        return new Itinerary($trip->id, count($schedule), $schedule);
    }

    /**
     * Generate full route (uses sync + prefs + votes)
     */
    public function generateFullRoute(Trip $trip, int $days, int $radius): Itinerary
    {
        $existingPlaces = Place::near($trip->start_latitude, $trip->start_longitude, $radius)->count();
        $syncedCount = 0;

        if ($existingPlaces < 10) {
            $syncedCount = $this->placesSync->fetchAndStore($trip->start_latitude, $trip->start_longitude, $radius);
        }

        $prefs = $this->aggregator->getGroupPreferences($trip);

        $votes = DB::table('trip_place_votes')
            ->select('place_id', DB::raw('AVG(score) as avg_score'))
            ->where('trip_id', $trip->id)
            ->groupBy('place_id')
            ->pluck('avg_score', 'place_id');

        $fixedPlaces = $trip->places()
            ->wherePivot('is_fixed', true)
            ->get(['places.id', 'places.name', 'places.category_slug']);

        $places = Place::near($trip->start_latitude, $trip->start_longitude, $radius)->get();

        $scored = $places->map(function ($place) use ($prefs, $votes) {
            $prefScore = $prefs[$place->category_slug] ?? 0.5;
            $voteScore = $votes[$place->id] ?? 0;
            $place->score = round(($prefScore * 2.0) + ($voteScore * 1.0) + (($place->rating ?? 0) * 0.8), 2);
            return $place;
        })->sortByDesc('score')->values();

        $perDay = (int) ceil(max(1, $scored->count()) / max(1, $days));

        $schedule = [];
        for ($day = 1; $day <= $days; $day++) {
            $anchors = $fixedPlaces->slice($day - 1, 1)->values()->all();
            $chunk = $scored->slice(($day - 1) * $perDay, $perDay)->values()->all();
            $schedule[] = new ItineraryDay($day, array_values(array_merge($anchors, $chunk)));
        }

        TripItinerary::updateOrCreate(
            ['trip_id' => $trip->id],
            [
                'schedule' => collect($schedule)->toArray(),
                'day_count' => $days,
                'generated_at' => now(),
            ]
        );

        return new Itinerary(
            $trip->id,
            $days,
            $schedule,
            ['synced_places' => $syncedCount]
        );
    }
}
