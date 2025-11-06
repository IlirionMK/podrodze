<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\UserPreference;
use App\Models\Place;
use App\Models\TripItinerary;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\PreferenceAggregatorService;
use App\Services\PlacesSyncService;

class ItineraryController extends Controller
{
    use AuthorizesRequests;

    /**
     * @group Trips / Itinerary
     *
     * Get aggregated group preferences (simplified)
     */
    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

        $acceptedUserIds = $trip->members()
            ->wherePivot('status', 'accepted')
            ->pluck('users.id');

        if ($acceptedUserIds->isEmpty()) {
            return response()->json([
                'message' => 'No accepted members for this trip yet.'
            ], 200);
        }

        $aggregated = UserPreference::query()
            ->selectRaw('category_id, AVG(score) as avg_score, COUNT(*) as votes')
            ->whereIn('user_id', $acceptedUserIds)
            ->groupBy('category_id')
            ->with('category:id,slug,translations')
            ->get()
            ->filter(fn($pref) => $pref->category)
            ->map(function ($pref) {
                return [
                    'category'  => $pref->category->slug,
                    'avg_score' => round($pref->avg_score, 2),
                    'votes'     => $pref->votes,
                    'name'      => $pref->category->translations['en']
                        ?? $pref->category->slug,
                ];
            })
            ->sortByDesc('avg_score')
            ->values();

        return response()->json($aggregated);
    }

    /**
     * @group Trips / Itinerary
     *
     * Get aggregated group preferences (simplified).
     */
    public function aggregatePreferences(Trip $trip, PreferenceAggregatorService $aggregator)
    {
        $this->authorize('view', $trip);

        $groupPrefs = $aggregator->getGroupPreferences($trip);

        return response()->json([
            'trip_id' => $trip->id,
            'group_preferences' => $groupPrefs,
        ]);
    }

    /**
     * @group Trips / Itinerary
     *
     * Generate a recommended itinerary for a trip.
     */
    public function generate(Trip $trip, PreferenceAggregatorService $aggregator)
    {
        $this->authorize('view', $trip);

        if (!$trip->start_latitude || !$trip->start_longitude) {
            return response()->json([
                'message' => 'Trip has no start location set. Please define start_latitude and start_longitude first.'
            ], 400);
        }

        $itineraryCacheKey = "itinerary:trip:{$trip->id}";

        return Cache::remember($itineraryCacheKey, now()->addHours(6), function () use ($trip, $aggregator) {
            $prefs = $aggregator->getGroupPreferences($trip);
            if (empty($prefs)) {
                return response()->json(['message' => 'No group preferences available'], 200);
            }

            $centerLat = $trip->start_latitude;
            $centerLon = $trip->start_longitude;
            $radius = 2000;

            $placesCacheKey = "places:trip:{$trip->id}";

            $places = Cache::remember($placesCacheKey, now()->addHours(12), function () use ($trip, $centerLon, $centerLat, $radius) {
                return Place::select([
                    'places.id',
                    'places.name',
                    'places.category_slug',
                    'places.rating',
                    DB::raw("ST_Distance(location::geography, ST_SetSRID(ST_MakePoint($centerLon, $centerLat), 4326)::geography) AS distance_m")
                ])
                    ->whereRaw("ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)", [$centerLon, $centerLat, $radius])
                    ->get();
            });

            $scored = $places->map(function ($place) use ($prefs) {
                $prefScore = $prefs[$place->category_slug] ?? 0.5;
                $place->score = round(
                    ($prefScore * 2.0) +
                    (($place->rating ?? 0) * 0.8) -
                    ($place->distance_m / 1500),
                    2
                );
                return $place;
            })->sortByDesc('score')->values();

            return response()->json([
                'trip_id' => $trip->id,
                'center' => [
                    'lat' => $centerLat,
                    'lon' => $centerLon,
                ],
                'suggested_places' => $scored->take(10)->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'category_slug' => $p->category_slug,
                    'score' => $p->score,
                    'distance_m' => round($p->distance_m),
                ]),
                'cache_info' => [
                    'itinerary_ttl_h' => 6,
                    'places_ttl_h' => 12,
                ]
            ]);
        });
    }

    /**
     * @group Trips / Itinerary
     *
     * Generate and persist full itinerary for a trip.
     */
    public function full(Trip $trip)
    {
        $this->authorize('view', $trip);

        $itinerary = Cache::remember("itinerary:trip:{$trip->id}:full", now()->addHours(6), function () use ($trip) {
            $places = Cache::get("places:trip:{$trip->id}");
            if (!$places) {
                return response()->json(['message' => 'No cached places for this trip'], 404);
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
                $schedule[] = [
                    'day' => $day++,
                    'stops' => $cluster['places'],
                ];
            }

            return TripItinerary::updateOrCreate(
                ['trip_id' => $trip->id],
                [
                    'schedule' => $schedule,
                    'day_count' => count($schedule),
                    'generated_at' => now(),
                ]
            );
        });

        return response()->json([
            'trip_id' => $trip->id,
            'day_count' => $itinerary->day_count ?? 0,
            'schedule' => $itinerary->schedule ?? [],
            'cached' => true,
        ]);
    }

    /**
     * @group Trips / Itinerary
     *
     * Generate full itinerary based on preferences, votes, and fixed places.
     */
    public function generateFullRoute(
        Request $request,
        Trip $trip,
        PreferenceAggregatorService $aggregator,
        PlacesSyncService $sync
    ) {
        $this->authorize('view', $trip);

        if (!$trip->start_latitude || !$trip->start_longitude) {
            return response()->json(['message' => 'Trip has no start location set.'], 400);
        }

        $days = (int) $request->input('days', 2);
        $radius = (int) $request->input('radius', 2000);

        $existingPlaces = Place::near($trip->start_latitude, $trip->start_longitude, $radius)->count();
        $syncedCount = 0;
        if ($existingPlaces < 10) {
            $syncedCount = $sync->fetchAndStore($trip->start_latitude, $trip->start_longitude, $radius);
        }

        $prefs = $aggregator->getGroupPreferences($trip);

        $votes = DB::table('trip_place_votes')
            ->select('place_id', DB::raw('AVG(score) as avg_score'))
            ->where('trip_id', $trip->id)
            ->groupBy('place_id')
            ->pluck('avg_score', 'place_id');

        $fixedPlaces = $trip->places()->wherePivot('is_fixed', true)->get(['places.id', 'places.name', 'places.category_slug']);

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
            $schedule[] = [
                'day' => $day,
                'stops' => array_values(array_merge($anchors, $chunk)),
            ];
        }

        $itinerary = TripItinerary::updateOrCreate(
            ['trip_id' => $trip->id],
            [
                'schedule' => $schedule,
                'day_count' => $days,
                'generated_at' => now(),
            ]
        );

        return response()->json([
            'trip_id' => $trip->id,
            'days' => $days,
            'synced_places' => $syncedCount,
            'schedule' => $schedule,
        ]);
    }
}
