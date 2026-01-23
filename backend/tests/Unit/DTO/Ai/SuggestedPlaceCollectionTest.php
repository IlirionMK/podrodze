<?php

namespace Tests\Unit\DTO\Ai;

use App\DTO\Ai\SuggestedPlace;
use App\DTO\Ai\SuggestedPlaceCollection;
use PHPUnit\Framework\TestCase;

class SuggestedPlaceCollectionTest extends TestCase
{
    public function test_it_creates_collection_with_places()
    {
        $place1 = new SuggestedPlace(
            source: 'google_places',
            internalPlaceId: 1,
            externalId: 'place1',
            name: 'Place 1',
            category: 'restaurant',
            rating: 4.5,
            reviewsCount: 100,
            lat: 52.2297,
            lon: 21.0122,
            distanceMeters: 500,
            nearPlaceName: 'Warsaw',
            estimatedVisitMinutes: 60,
            score: 0.9,
            reason: 'Test place 1',
            addPayload: []
        );

        $place2 = new SuggestedPlace(
            source: 'google_places',
            internalPlaceId: 2,
            externalId: 'place2',
            name: 'Place 2',
            category: 'cafe',
            rating: 4.7,
            reviewsCount: 50,
            lat: 52.2300,
            lon: 21.0130,
            distanceMeters: 300,
            nearPlaceName: 'Warsaw',
            estimatedVisitMinutes: 30,
            score: 0.85,
            reason: 'Test place 2',
            addPayload: []
        );

        $meta = ['total' => 2, 'page' => 1];
        $collection = new SuggestedPlaceCollection([$place1, $place2], $meta);

        $this->assertCount(2, $collection->items);
        $this->assertSame($place1, $collection->items[0]);
        $this->assertSame($place2, $collection->items[1]);
        $this->assertEquals($meta, $collection->meta);
    }

    public function test_it_creates_collection_with_empty_items()
    {
        $collection = new SuggestedPlaceCollection([], ['total' => 0]);
        
        $this->assertEmpty($collection->items);
        $this->assertEquals(['total' => 0], $collection->meta);
    }

    public function test_it_creates_collection_without_meta()
    {
        $collection = new SuggestedPlaceCollection([]);
        
        $this->assertEmpty($collection->items);
        $this->assertEmpty($collection->meta);
    }
}
