<?php

namespace Tests\Unit\DTO\Ai;

use App\DTO\Ai\SuggestedPlace;
use PHPUnit\Framework\TestCase;

class SuggestedPlaceTest extends TestCase
{
    public function test_it_creates_suggested_place()
    {
        $place = new SuggestedPlace(
            source: 'google_places',
            internalPlaceId: 123,
            externalId: 'abc123',
            name: 'Test Place',
            category: 'restaurant',
            rating: 4.5,
            reviewsCount: 100,
            lat: 52.2297,
            lon: 21.0122,
            distanceMeters: 500,
            nearPlaceName: 'Warsaw',
            estimatedVisitMinutes: 60,
            score: 0.9,
            reason: 'Highly rated place',
            addPayload: ['photos' => ['url1', 'url2']]
        );

        $this->assertEquals('google_places', $place->source);
        $this->assertEquals(123, $place->internalPlaceId);
        $this->assertEquals('abc123', $place->externalId);
        $this->assertEquals('Test Place', $place->name);
        $this->assertEquals('restaurant', $place->category);
        $this->assertEquals(4.5, $place->rating);
        $this->assertEquals(100, $place->reviewsCount);
        $this->assertEquals(52.2297, $place->lat);
        $this->assertEquals(21.0122, $place->lon);
        $this->assertEquals(500, $place->distanceMeters);
        $this->assertEquals('Warsaw', $place->nearPlaceName);
        $this->assertEquals(60, $place->estimatedVisitMinutes);
        $this->assertEquals(0.9, $place->score);
        $this->assertEquals('Highly rated place', $place->reason);
        $this->assertEquals(['photos' => ['url1', 'url2']], $place->addPayload);
    }

    public function test_it_allows_updating_reason()
    {
        $place = new SuggestedPlace(
            source: 'google_places',
            internalPlaceId: 123,
            externalId: 'abc123',
            name: 'Test Place',
            category: 'restaurant',
            rating: 4.5,
            reviewsCount: 100,
            lat: 52.2297,
            lon: 21.0122,
            distanceMeters: 500,
            nearPlaceName: 'Warsaw',
            estimatedVisitMinutes: 60,
            score: 0.9,
            reason: 'Initial reason',
            addPayload: []
        );

        $place->reason = 'Updated reason';
        
        $this->assertEquals('Updated reason', $place->reason);
    }
}
