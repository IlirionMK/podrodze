<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\SuggestedPlaceResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuggestedPlaceResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_suggested_place_to_array(): void
    {
        $place = new \stdClass();
        $place->source = 'google_places';
        $place->internalPlaceId = 123;
        $place->externalId = 'ChIJ';
        $place->name = 'Central Park';
        $place->category = 'park';
        $place->rating = 4.7;
        $place->reviewsCount = 15420;
        $place->lat = 40.7829;
        $place->lon = -73.9654;
        $place->distanceMeters = 850;
        $place->estimatedVisitMinutes = 120;
        $place->score = 4.5;
        $place->reason = 'Highly rated park near your location';
        $place->addPayload = ['trip_id' => 1, 'day' => 2];

        $resource = new SuggestedPlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertEquals('google_places', $result['source']);
        $this->assertEquals(123, $result['internal_place_id']);
        $this->assertEquals('ChIJ', $result['external_id']);
        $this->assertEquals('Central Park', $result['name']);
        $this->assertEquals('park', $result['category']);
        $this->assertEquals(4.7, $result['rating']);
        $this->assertEquals(15420, $result['reviews_count']);
        $this->assertEquals(850, $result['distance_m']);
        $this->assertEquals(120, $result['estimated_visit_minutes']);
        $this->assertEquals(4.5, $result['score']);
        $this->assertEquals('Highly rated park near your location', $result['reason']);

        // Check location structure
        $this->assertEquals(40.7829, $result['location']['lat']);
        $this->assertEquals(-73.9654, $result['location']['lon']);

        // Check actions structure
        $this->assertEquals(['trip_id' => 1, 'day' => 2], $result['actions']['add_payload']);
    }

    #[Test]
    public function it_handles_null_values(): void
    {
        $place = new \stdClass();
        $place->source = null;
        $place->internalPlaceId = null;
        $place->externalId = null;
        $place->name = null;
        $place->category = null;
        $place->rating = null;
        $place->reviewsCount = null;
        $place->lat = null;
        $place->lon = null;
        $place->distanceMeters = null;
        $place->estimatedVisitMinutes = null;
        $place->score = null;
        $place->reason = null;
        $place->addPayload = null;

        $resource = new SuggestedPlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertNull($result['source']);
        $this->assertNull($result['internal_place_id']);
        $this->assertNull($result['external_id']);
        $this->assertNull($result['name']);
        $this->assertNull($result['category']);
        $this->assertNull($result['rating']);
        $this->assertNull($result['reviews_count']);
        $this->assertNull($result['location']['lat']);
        $this->assertNull($result['location']['lon']);
        $this->assertNull($result['distance_m']);
        $this->assertNull($result['estimated_visit_minutes']);
        $this->assertNull($result['score']);
        $this->assertNull($result['reason']);
        $this->assertNull($result['actions']['add_payload']);
    }

    #[Test]
    public function it_handles_numeric_string_values(): void
    {
        $place = new \stdClass();
        $place->source = 'osm';
        $place->internalPlaceId = '456';
        $place->externalId = 'node/123';
        $place->name = 'Brooklyn Bridge';
        $place->category = 'landmark';
        $place->rating = '4.8';
        $place->reviewsCount = '8932';
        $place->lat = '40.7061';
        $place->lon = '-73.9969';
        $place->distanceMeters = '2100';
        $place->estimatedVisitMinutes = '60';
        $place->score = '4.6';
        $place->reason = 'Iconic landmark with great views';
        $place->addPayload = ['trip_id' => '2'];

        $resource = new SuggestedPlaceResource($place);
        $result = $resource->toArray(request());

        $this->assertEquals('osm', $result['source']);
        $this->assertEquals('456', $result['internal_place_id']);
        $this->assertEquals('node/123', $result['external_id']);
        $this->assertEquals('Brooklyn Bridge', $result['name']);
        $this->assertEquals('landmark', $result['category']);
        $this->assertEquals('4.8', $result['rating']);
        $this->assertEquals('8932', $result['reviews_count']);
        $this->assertEquals('40.7061', $result['location']['lat']);
        $this->assertEquals('-73.9969', $result['location']['lon']);
        $this->assertEquals('2100', $result['distance_m']);
        $this->assertEquals('60', $result['estimated_visit_minutes']);
        $this->assertEquals('4.6', $result['score']);
        $this->assertEquals('Iconic landmark with great views', $result['reason']);
        $this->assertEquals(['trip_id' => '2'], $result['actions']['add_payload']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $place = new \stdClass();
        $place->source = 'test';
        $place->internalPlaceId = 1;
        $place->externalId = 'ext1';
        $place->name = 'Test Place';
        $place->category = 'test_category';
        $place->rating = 4.0;
        $place->reviewsCount = 100;
        $place->lat = 0.0;
        $place->lon = 0.0;
        $place->distanceMeters = 0;
        $place->estimatedVisitMinutes = 30;
        $place->score = 3.0;
        $place->reason = 'Test reason';
        $place->addPayload = [];

        $resource = new SuggestedPlaceResource($place);
        $result = $resource->toArray(request());

        $expectedKeys = [
            'source', 'internal_place_id', 'external_id', 'name', 'category',
            'rating', 'reviews_count', 'location', 'distance_m',
            'estimated_visit_minutes', 'score', 'reason', 'actions'
        ];
        $this->assertEquals($expectedKeys, array_keys($result));

        $locationKeys = ['lat', 'lon'];
        $this->assertEquals($locationKeys, array_keys($result['location']));

        $actionsKeys = ['add_payload'];
        $this->assertEquals($actionsKeys, array_keys($result['actions']));
    }

    #[Test]
    public function it_handles_complex_add_payload(): void
    {
        $place = new \stdClass();
        $place->source = 'manual';
        $place->internalPlaceId = 999;
        $place->externalId = 'custom_999';
        $place->name = 'Custom Place';
        $place->category = 'custom';
        $place->rating = 5.0;
        $place->reviewsCount = 0;
        $place->lat = 51.5074;
        $place->lon = -0.1278;
        $place->distanceMeters = 0;
        $place->estimatedVisitMinutes = 45;
        $place->score = 5.0;
        $place->reason = 'User suggested place';
        $place->addPayload = [
            'trip_id' => 3,
            'day' => 1,
            'position' => 2,
            'notes' => 'Visit during morning hours',
            'priority' => 'high',
        ];

        $resource = new SuggestedPlaceResource($place);
        $result = $resource->toArray(request());

        $expectedPayload = [
            'trip_id' => 3,
            'day' => 1,
            'position' => 2,
            'notes' => 'Visit during morning hours',
            'priority' => 'high',
        ];
        $this->assertEquals($expectedPayload, $result['actions']['add_payload']);
    }
}
