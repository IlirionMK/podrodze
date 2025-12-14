<?php

namespace App\Services;

use App\DTO\Trip\TripPlace;
use App\DTO\Trip\TripVote;
use App\Interfaces\PlaceInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use DomainException;

class PlaceService implements PlaceInterface
{
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

    /**
     * Create a manually added custom place (no Google ID).
     */
    public function createCustomPlace(array $data, User $user): Place
    {
        return Place::create([
            'name'          => $data['name'],
            'category_slug' => $data['category'],
            'location'      => DB::raw("ST_SetSRID(ST_MakePoint({$data['lon']}, {$data['lat']}), 4326)"),
            'meta'          => [
                'source'     => 'custom',
                'created_by' => $user->id,
            ],
        ]);
    }

    /**
     * Map Google Place types to internal category.
     */
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

    /**
     * Create a place from Google data (for later Google integration).
     */
    public function createFromGoogle(array $data, User $user): Place
    {
        $category = $this->mapGoogleTypesToCategory($data['types'] ?? []);

        return Place::create([
            'name'           => $data['name'],
            'google_place_id'=> $data['google_place_id'] ?? null,
            'category_slug'  => $category,
            'rating'         => $data['rating'] ?? null,
            'location'       => DB::raw("ST_SetSRID(ST_MakePoint({$data['lon']}, {$data['lat']}), 4326)"),
            'opening_hours'  => $data['opening_hours'] ?? null,
            'meta'           => [
                'source' => 'google',
            ],
        ]);
    }

    /**
     * Universal entry point for attaching places to a trip.
     *
     * If place_id is provided → attach existing place.
     * Else → create new custom place and attach it.
     */
    public function addToTrip(Trip $trip, array $data, User $user): TripPlace
    {
        // Existing place
        if (!empty($data['place_id'])) {
            return $this->attachToTrip($trip, $data, $user);
        }

        // Create custom place
        $place = $this->createCustomPlace($data, $user);

        $data['place_id'] = $place->id;

        return $this->attachToTrip($trip, $data, $user);
    }

    /**
     * Attach place to trip.
     *
     * @throws ModelNotFoundException
     * @throws DomainException
     */
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

        $trip->places()->attach($placeId, [
            'status'      => $data['status'] ?? 'planned',
            'is_fixed'    => $data['is_fixed'] ?? false,
            'day'         => $data['day'] ?? null,
            'order_index' => $data['order_index'] ?? null,
            'note'        => $data['note'] ?? null,
            'added_by'    => $user->id,
        ]);

        $attached = $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->findOrFail($placeId);

        return TripPlace::fromModel($attached);
    }

    /**
     * @throws ModelNotFoundException
     */
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

    /**
     * @throws ModelNotFoundException
     */
    public function detachFromTrip(Trip $trip, Place $place): void
    {
        if (!$trip->places()->where('places.id', $place->id)->exists()) {
            throw new ModelNotFoundException('Place not found in this trip.');
        }

        $trip->places()->detach($place->id);
    }

    public function saveTripVote(Trip $trip, Place $place, User $user, int $score): TripVote
    {
        DB::table('trip_place_votes')->updateOrInsert(
            [
                'trip_id'  => $trip->id,
                'place_id' => $place->id,
                'user_id'  => $user->id,
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
     * Nearby place search (PostGIS).
     *
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
