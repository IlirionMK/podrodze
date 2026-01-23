<?php

namespace Tests\Unit\DTO\Itinerary;

use App\DTO\Itinerary\ItineraryDay;
use App\DTO\Itinerary\ItineraryPlace;
use PHPUnit\Framework\TestCase;

class ItineraryDayTest extends TestCase
{
    public function test_it_creates_itinerary_day()
    {
        $place1 = new ItineraryPlace(
            id: 1,
            name: 'Place 1',
            category_slug: 'restaurant',
            score: 0.9,
            distance_m: 500.0
        );
        
        $day = new ItineraryDay(
            day: 1,
            places: [$place1]
        );

        $this->assertEquals(1, $day->day);
        $this->assertCount(1, $day->places);
        $this->assertSame($place1, $day->places[0]);
    }

    public function test_it_serializes_to_json()
    {
        $place1 = new ItineraryPlace(
            id: 1,
            name: 'Place 1',
            category_slug: 'restaurant',
            score: 0.9,
            distance_m: 500.0
        );
        
        $day = new ItineraryDay(
            day: 1,
            places: [$place1]
        );

        $expected = [
            'day' => 1,
            'places' => [$place1],
        ];

        $this->assertEquals($expected, $day->jsonSerialize());
    }
}
