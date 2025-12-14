<?php

namespace App\Services;

use App\DTO\Trip\TripPlace;
use App\DTO\Trip\TripVote;
use App\Interfaces\PlaceInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PlaceService implements PlaceInterface
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {}

    /**
     * @return Collection<int, TripPlace>
     */
    public function listForTrip(Trip $trip): Collection
    {
        return $trip->places()
            ->select('places.*')
            ->addSelect([
                DB::raw('ST_Y(places.location::geometry) AS lat'),
                DB::raw('ST_X(places.location::geometry) AS lon'),
            ])
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->get()
            ->map(fn (Place $p) => TripPlace::fromModel($p));
    }

    public function createCustomPlace(array $data, User $user): Place
    {
        return Place::create([
            'name' => $data['name'],
            'category_slug' => $data['category'],
            'location' => DB::raw("ST_SetSRID(ST_MakePoint({$data['lon']}, {$data['lat']}), 4326)"),
            'meta' => [
                'source' => 'custom',
                'created_by' => $user->id,
            ],
        ]);
    }

    private function mapGoogleTypesToCategory(array $types): string
    {
        foreach ($types as $type) {
            $mapped = Config::get("google_category_map.$type");
            if ($mapped) {
                return $mapped;
            }
        }

        return 'other';
    }

    public function createFromGoogle(array $data, User $user): Place
    {
        $category = $this->mapGoogleTypesToCategory($data['types'] ?? []);

        return Place::create([
            'name' => $data['name'],
            'google_place_id' => $data['google_place_id'] ?? null,
            'category_slug' => $category,
            'rating' => $data['rating'] ?? null,
            'location' => DB::raw("ST_SetSRID(ST_MakePoint({$data['lon']}, {$data['lat']}), 4326)"),
            'opening_hours' => $data['opening_hours'] ?? null,
            'meta' => [
                'source' => 'google',
            ],
        ]);
    }

    public function addToTrip(Trip $trip, array $data, User $user): TripPlace
    {
        if (!empty($data['place_id'])) {
            return $this->attachToTrip($trip, $data, $user);
        }

        $place = $this->createCustomPlace($data, $user);

        $data['place_id'] = $place->id;

        return $this->attachToTrip($trip, $data, $user);
    }

    public function attachToTrip(Trip $trip, array $data, User $user): TripPlace
    {
        $placeId = (int) $data['place_id'];

        $place = Place::find($placeId);

        if (!$place) {
            throw new ModelNotFoundException("Place #{$placeId} not found.");
        }

        if ($trip->places()->where('places.id', $placeId)->exists()) {
            throw new DomainException('This place is already attached to the trip.');
        }

        $pivot = [
            'status' => $data['status'] ?? 'planned',
            'is_fixed' => (bool) ($data['is_fixed'] ?? false),
            'day' => $data['day'] ?? null,
            'order_index' => $data['order_index'] ?? null,
            'note' => $data['note'] ?? null,
            'added_by' => $user->id,
        ];

        $trip->places()->attach($placeId, $pivot);

        $attached = $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->findOrFail($placeId);

        $this->activityLogger->add(
            actor: $user,
            action: 'trip.place_added',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'place_id' => $place->getKey(),
                'place_name' => (string) $place->getAttribute('name'),
                'status' => $pivot['status'],
                'is_fixed' => $pivot['is_fixed'],
                'day' => $pivot['day'],
                'order_index' => $pivot['order_index'],
                'added_by' => $user->getKey(),
            ]
        );

        return TripPlace::fromModel($attached);
    }

    public function updateTripPlace(Trip $trip, Place $place, array $data): TripPlace
    {
        $attached = $trip->places()->where('places.id', $place->id)->first();

        if (!$attached) {
            throw new ModelNotFoundException('Place not found in this trip.');
        }

        $trip->places()->updateExistingPivot($place->id, $data);

        $updated = $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->findOrFail($place->id);

        return TripPlace::fromModel($updated);
    }

    public function detachFromTrip(Trip $trip, Place $place, User $user): void
    {
        if (!$trip->places()->where('places.id', $place->id)->exists()) {
            throw new ModelNotFoundException('Place not found in this trip.');
        }

        $trip->places()->detach($place->id);

        $this->activityLogger->add(
            actor: $user,
            action: 'trip.place_removed',
            target: $trip,
            details: [
                'trip_id' => $trip->getKey(),
                'place_id' => $place->getKey(),
                'place_name' => (string) $place->getAttribute('name'),
                'removed_by' => $user->getKey(),
            ]
        );
    }

    public function saveTripVote(Trip $trip, Place $place, User $user, int $score): TripVote
    {
        DB::table('trip_place_votes')->updateOrInsert(
            [
                'trip_id' => $trip->id,
                'place_id' => $place->id,
                'user_id' => $user->id,
            ],
            ['score' => $score]
        );

        $aggregate = DB::table('trip_place_votes')
            ->selectRaw('AVG(score) as avg_score, COUNT(*) as votes')
            ->where('trip_id', $trip->id)
            ->where('place_id', $place->id)
            ->first();

        return TripVote::fromAggregate($aggregate);
    }

    /**
     * @return Collection<int, Place>
     */
    public function findNearby(float $lat, float $lon, int $radius = 2000): Collection
    {
        return Place::query()
            ->select('places.*')
            ->addSelect([
                DB::raw('ST_Y(location::geometry) AS lat'),
                DB::raw('ST_X(location::geometry) AS lon'),
                DB::raw("
                    ST_Distance(
                        location::geography,
                        ST_SetSRID(ST_MakePoint({$lon}, {$lat}), 4326)::geography
                    ) AS distance_m
                "),
            ])
            ->whereRaw("
                ST_DWithin(
                    location::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    ?
                )
            ", [$lon, $lat, $radius])
            ->orderBy('distance_m')
            ->get();
    }
}
