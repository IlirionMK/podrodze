<?php

namespace Tests\Unit\DTO\Itinerary;

use App\DTO\Itinerary\ItineraryPlace;
use PHPUnit\Framework\TestCase;

class ItineraryPlaceTest extends TestCase
{
    public function test_it_creates_itinerary_place()
    {
        $itineraryPlace = new ItineraryPlace(
            id: 1,
            name: 'Test Place',
            category_slug: 'attraction',
            score: 0.8,
            distance_m: 150.5
        );

        $this->assertEquals(1, $itineraryPlace->id);
        $this->assertEquals('Test Place', $itineraryPlace->name);
        $this->assertEquals('attraction', $itineraryPlace->category_slug);
        $this->assertEquals(0.8, $itineraryPlace->score);
        $this->assertEquals(150.5, $itineraryPlace->distance_m);
    }

    public function test_it_serializes_to_json()
    {
        $itineraryPlace = new ItineraryPlace(
            id: 1,
            name: 'Test Place',
            category_slug: 'attraction',
            score: 0.8,
            distance_m: 150.5
        );

        $expected = [
            'id' => 1,
            'name' => 'Test Place',
            'category_slug' => 'attraction',
            'score' => 0.8,
            'distance_m' => 150.5,
        ];

        $this->assertEquals($expected, $itineraryPlace->jsonSerialize());
    }
}
