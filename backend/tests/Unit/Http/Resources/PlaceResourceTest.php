<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PlaceResource;
use App\Models\Place;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlaceResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_place_to_array(): void
    {
        $place = Place::factory()->create([
            'id' => 100,
            'google_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'name' => 'Sydney Opera House',
            'category_slug' => 'landmark',
            'rating' => 4.8,
            'meta' => json_encode(['user_ratings_total' => 15000, 'photos' => ['photo1.jpg']]),
            'location' => 'POINT(-33.8568 151.2153)',
            'created_at' => '2024-01-01 12:00:00',
            'updated_at' => '2024-01-01 12:00:00',
        ]);
        
        // Mock the lat, lon, and distance_m properties for testing
        $place->lat = -33.8568;
        $place->lon = 151.2153;
        $place->distance_m = 150.567;

        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertEquals(100, $result['id']);
        $this->assertEquals('ChIJN1t_tDeuEmsRUsoyG83frY4', $result['google_place_id']);
        $this->assertEquals('Sydney Opera House', $result['name']);
        $this->assertEquals('landmark', $result['category_slug']);
        $this->assertEquals(4.8, $result['rating']);
        $this->assertEquals(['user_ratings_total' => 15000, 'photos' => ['photo1.jpg']], $result['meta']);
        $this->assertEquals(-33.8568, $result['lat']);
        $this->assertEquals(151.2153, $result['lon']);
        $this->assertEquals(150.6, $result['distance_m']); // Rounded to 1 decimal place
        $this->assertEquals('2024-01-01T12:00:00.000000Z', $result['created_at']);
        $this->assertEquals('2024-01-01T12:00:00.000000Z', $result['updated_at']);
    }

    #[Test]
    public function it_handles_null_coordinates(): void
    {
        $place = Place::factory()->create();
        
        // Mock the lat and lon properties for testing
        $place->lat = null;
        $place->lon = null;

        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertNull($result['lat']);
        $this->assertNull($result['lon']);
    }

    #[Test]
    public function it_handles_null_distance(): void
    {
        $place = Place::factory()->create();
        
        // Mock the distance_m property for testing
        $place->distance_m = null;

        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertNull($result['distance_m']);
    }

    #[Test]
    public function it_handles_meta_as_string(): void
    {
        $place = Place::factory()->create([
            'meta' => '{"user_ratings_total": 500}',
        ]);

        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertEquals(['user_ratings_total' => 500], $result['meta']);
    }

    #[Test]
    public function it_handles_meta_as_array(): void
    {
        $place = Place::factory()->create([
            'meta' => ['user_ratings_total' => 500],
        ]);

        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertEquals(['user_ratings_total' => 500], $result['meta']);
    }

    #[Test]
    public function it_handles_null_timestamps(): void
    {
        $place = Place::factory()->create([
            'created_at' => null,
            'updated_at' => null,
        ]);

        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertNull($result['created_at']);
        $this->assertNull($result['updated_at']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $place = Place::factory()->create();

        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $expectedKeys = [
            'id', 'google_place_id', 'name', 'category_slug', 'rating',
            'meta', 'lat', 'lon', 'distance_m', 'created_at', 'updated_at'
        ];
        $this->assertEquals($expectedKeys, array_keys($result));
    }

    #[Test]
    public function it_rounds_distance_correctly(): void
    {
        $place = Place::factory()->create();
        
        // Mock the distance_m property for testing
        $place->distance_m = 150.567;

        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertEquals(150.6, $result['distance_m']);

        $place->distance_m = 150.543;
        $resource = new PlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertEquals(150.5, $result['distance_m']);
    }
}
