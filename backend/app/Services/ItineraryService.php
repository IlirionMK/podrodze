<?php

namespace App\Services;

use App\DTO\Itinerary\Itinerary;
use App\DTO\Itinerary\ItineraryDay;
use App\DTO\Itinerary\ItineraryPlace;
use App\Interfaces\ItineraryServiceInterface;
use App\Interfaces\PreferenceAggregatorServiceInterface;
use App\Models\Trip;
use App\Models\TripItinerary;
use App\Services\Activity\ActivityLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class ItineraryService implements ItineraryServiceInterface
{
    public function __construct(
        protected PreferenceAggregatorServiceInterface $aggregator,
        private readonly ActivityLogger $activityLogger
    ) {}

    public function aggregatePreferences(Trip $trip): array
    {
        return $this->aggregator->getGroupPreferences($trip);
    }

    protected function resolveOrigin(Trip $trip): array
    {
        $fixedLodging = $trip->places()
            ->wherePivot('is_fixed', true)
            ->where('category_slug', 'lodging')
            ->first();

        if ($fixedLodging) {
            $coords = DB::table('places')
                ->selectRaw('ST_Y(location::geometry) as lat, ST_X(location::geometry) as lon')
                ->where('id', $fixedLodging->id)
                ->first();

            if ($coords && $coords->lat !== null && $coords->lon !== null) {
                return [
                    'lat'      => (float) $coords->lat,
                    'lon'      => (float) $coords->lon,
                    'source'   => 'fixed_lodging',
                    'place_id' => (int) $fixedLodging->id,
                ];
            }
        }

        $fixedAny = $trip->places()
            ->wherePivot('is_fixed', true)
            ->first();

        if ($fixedAny) {
            $coords = DB::table('places')
                ->selectRaw('ST_Y(location::geometry) as lat, ST_X(location::geometry) as lon')
                ->where('id', $fixedAny->id)
                ->first();

            if ($coords && $coords->lat !== null && $coords->lon !== null) {
                return [
                    'lat'      => (float) $coords->lat,
                    'lon'      => (float) $coords->lon,
                    'source'   => 'fixed_any',
                    'place_id' => (int) $fixedAny->id,
                ];
            }
        }

        if ($trip->start_latitude && $trip->start_longitude) {
            return [
                'lat'      => (float) $trip->start_latitude,
                'lon'      => (float) $trip->start_longitude,
                'source'   => 'trip_start',
                'place_id' => null,
            ];
        }

        throw new DomainException('Trip has no origin point (no fixed places and no start location).');
    }

    protected function computeDistances(float $lat, float $lon, array $placeIds): array
    {
        if (empty($placeIds)) {
            return [];
        }

        return DB::table('places')
            ->selectRaw(
                'id, ST_DistanceSphere(location::geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326)) as distance_m',
                [$lon, $lat]
            )
            ->whereIn('id', $placeIds)
            ->pluck('distance_m', 'id')
            ->map(fn ($d) => (float) $d)
            ->all();
    }

    public function generate(Trip $trip): Itinerary
    {
        $origin = $this->resolveOrigin($trip);

        $tripPlaces = $trip->places()->get();

        if ($tripPlaces->isEmpty()) {
            throw new DomainException('No places added for this trip.');
        }

        $prefs = $this->aggregator->getGroupPreferences($trip);
        if (empty($prefs)) {
            throw new DomainException('No preferences available for this trip.');
        }

        $votes = DB::table('trip_place_votes')
            ->select('place_id', DB::raw('AVG(score) as avg_score'))
            ->where('trip_id', $trip->id)
            ->groupBy('place_id')
            ->pluck('avg_score', 'place_id');

        $distances = $this->computeDistances(
            $origin['lat'],
            $origin['lon'],
            $tripPlaces->pluck('id')->all()
        );

        $scored = $tripPlaces
            ->map(function ($p) use ($prefs, $votes, $distances) {
                $prefScore = (float) ($prefs[$p->category_slug] ?? 0.0);
                $voteScore = (float) ($votes[$p->id] ?? 0.0);
                $rating    = (float) ($p->rating ?? 0.0);
                $distance  = (float) ($distances[$p->id] ?? 0.0);

                $distancePenalty = $distance > 0 ? ($distance / 2000.0) : 0.0;

                $openBoost = 0.0;
                $opening   = $p->opening_hours ?? null;
                if (is_array($opening) && array_key_exists('open_now', $opening)) {
                    $openBoost = $opening['open_now'] ? 0.5 : -0.5;
                }

                $p->itinerary_distance_m = $distance;

                $p->itinerary_score = round(
                    ($prefScore * 2.0) +
                    ($voteScore * 1.0) +
                    ($rating * 0.5) +
                    $openBoost -
                    $distancePenalty,
                    2
                );

                return $p;
            })
            ->sortByDesc('itinerary_score')
            ->values();

        $top = $scored->take(10);

        $dtoPlaces = $top->map(function ($p) {
            return new ItineraryPlace(
                id:            $p->id,
                name:          $p->name,
                category_slug: $p->category_slug,
                score:         $p->itinerary_score ?? 0.0,
                distance_m:    (int) round($p->itinerary_distance_m ?? 0.0),
            );
        })->all();

        $itinerary = new Itinerary(
            trip_id:   $trip->id,
            day_count: 1,
            schedule:  [new ItineraryDay(1, $dtoPlaces)],
            cache_info: [
                'mode'      => 'simple_one_day',
                'source'    => 'trip_places_only',
                'algorithm' => 'v3-trip-places',
                'origin'    => [
                    'source'   => $origin['source'],
                    'place_id' => $origin['place_id'],
                ],
            ]
        );

        $this->activityLogger->add(
            actor: auth()->user(),
            action: 'trip.itinerary_generated',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'mode' => 'simple_one_day',
                'places_total' => (int) $tripPlaces->count(),
                'places_selected' => (int) count($dtoPlaces),
                'preferences_count' => (int) count($prefs),
                'votes_count' => (int) $votes->count(),
                'origin' => [
                    'source' => $origin['source'],
                    'place_id' => $origin['place_id'],
                ],
                'algorithm' => 'v3-trip-places',
            ]
        );

        return $itinerary;
    }

    public function generateFullRoute(Trip $trip, int $days, int $radius): Itinerary
    {
        $origin = $this->resolveOrigin($trip);

        $days = max(1, min($days, 30));

        $tripPlaces = $trip->places()
            ->withPivot(['is_fixed'])
            ->get();

        if ($tripPlaces->isEmpty()) {
            throw new DomainException('No places added for this trip.');
        }

        $prefs = $this->aggregator->getGroupPreferences($trip);
        if (empty($prefs)) {
            throw new DomainException('No preferences available for this trip.');
        }

        $votes = DB::table('trip_place_votes')
            ->select('place_id', DB::raw('AVG(score) as avg_score'))
            ->where('trip_id', $trip->id)
            ->groupBy('place_id')
            ->pluck('avg_score', 'place_id');

        $distances = $this->computeDistances(
            $origin['lat'],
            $origin['lon'],
            $tripPlaces->pluck('id')->all()
        );

        $scored = $tripPlaces->map(function ($p) use ($prefs, $votes, $distances) {
            $prefScore = (float) ($prefs[$p->category_slug] ?? 0.0);
            $voteScore = (float) ($votes[$p->id] ?? 0.0);
            $rating    = (float) ($p->rating ?? 0.0);
            $isFixed   = (bool) ($p->pivot?->is_fixed ?? false);
            $distance  = (float) ($distances[$p->id] ?? 0.0);

            $distancePenalty = $distance > 0 ? ($distance / 2000.0) : 0.0;

            $openBoost = 0.0;
            $opening   = $p->opening_hours ?? null;
            if (is_array($opening) && array_key_exists('open_now', $opening)) {
                $openBoost = $opening['open_now'] ? 0.5 : -0.5;
            }

            $fixedBoost = $isFixed ? 1.0 : 0.0;

            $p->itinerary_distance_m = $distance;

            $p->itinerary_score = round(
                ($prefScore * 2.0) +
                ($voteScore * 1.0) +
                ($rating * 0.5) +
                $openBoost +
                ($fixedBoost * 2.0) -
                $distancePenalty,
                2
            );

            return $p;
        });

        $fixed  = $scored->filter(fn ($p) => (bool) ($p->pivot?->is_fixed ?? false))
            ->sortByDesc('itinerary_score')
            ->values();

        $normal = $scored->reject(fn ($p) => (bool) ($p->pivot?->is_fixed ?? false))
            ->sortByDesc('itinerary_score')
            ->values();

        $dayBuckets = [];
        for ($d = 1; $d <= $days; $d++) {
            $dayBuckets[$d] = [
                'fixed'  => [],
                'normal' => [],
            ];
        }

        $index = 1;
        foreach ($fixed as $place) {
            $dayBuckets[$index]['fixed'][] = $place;
            $index++;
            if ($index > $days) {
                $index = 1;
            }
        }

        $index = 1;
        foreach ($normal as $place) {
            $dayBuckets[$index]['normal'][] = $place;
            $index++;
            if ($index > $days) {
                $index = 1;
            }
        }

        $schedule = [];

        for ($day = 1; $day <= $days; $day++) {
            $fixedForDay  = $dayBuckets[$day]['fixed'] ?? [];
            $normalForDay = $dayBuckets[$day]['normal'] ?? [];

            $placesForDay = array_merge($fixedForDay, $normalForDay);

            $dtoPlaces = array_map(
                fn ($p) => new ItineraryPlace(
                    id:            $p->id,
                    name:          $p->name,
                    category_slug: $p->category_slug,
                    score:         $p->itinerary_score ?? 0.0,
                    distance_m:    (int) round($p->itinerary_distance_m ?? 0.0),
                ),
                $placesForDay
            );

            $schedule[] = new ItineraryDay($day, $dtoPlaces);
        }

        TripItinerary::updateOrCreate(
            ['trip_id' => $trip->id],
            [
                'schedule'     => collect($schedule)->toArray(),
                'day_count'    => $days,
                'generated_at' => now(),
            ]
        );

        $itinerary = new Itinerary(
            trip_id:    $trip->id,
            day_count:  $days,
            schedule:   $schedule,
            cache_info: [
                'mode'      => 'multi_day',
                'source'    => 'trip_places_only',
                'algorithm' => 'v3-trip-places',
                'origin'    => [
                    'source'   => $origin['source'],
                    'place_id' => $origin['place_id'],
                ],
            ]
        );

        $this->activityLogger->add(
            actor: auth()->user(),
            action: 'trip.itinerary_full_generated',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'mode' => 'multi_day',
                'days' => $days,
                'radius' => $radius,
                'places_total' => (int) $tripPlaces->count(),
                'preferences_count' => (int) count($prefs),
                'votes_count' => (int) $votes->count(),
                'origin' => [
                    'source' => $origin['source'],
                    'place_id' => $origin['place_id'],
                ],
                'algorithm' => 'v3-trip-places',
                'cached' => true,
            ]
        );

        return $itinerary;
    }
}
