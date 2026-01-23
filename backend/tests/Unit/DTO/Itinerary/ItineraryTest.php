<?php

namespace Tests\Unit\DTO\Itinerary;

use App\DTO\Itinerary\Itinerary;
use App\DTO\Itinerary\ItineraryDay;
use PHPUnit\Framework\TestCase;

class ItineraryTest extends TestCase
{
    public function test_it_creates_itinerary()
    {
        $day1 = new ItineraryDay(1, []);
        $day2 = new ItineraryDay(2, []);
        
        $itinerary = new Itinerary(
            trip_id: 123,
            day_count: 2,
            schedule: [$day1, $day2],
            cache_info: ['cached' => true, 'ttl' => 3600]
        );

        $this->assertEquals(123, $itinerary->trip_id);
        $this->assertEquals(2, $itinerary->day_count);
        $this->assertCount(2, $itinerary->schedule);
        $this->assertSame($day1, $itinerary->schedule[0]);
        $this->assertSame($day2, $itinerary->schedule[1]);
        $this->assertEquals(['cached' => true, 'ttl' => 3600], $itinerary->cache_info);
    }

    public function test_it_serializes_to_json()
    {
        $day1 = new ItineraryDay(1, []);
        $day2 = new ItineraryDay(2, []);
        
        $itinerary = new Itinerary(
            trip_id: 123,
            day_count: 2,
            schedule: [$day1, $day2],
            cache_info: ['cached' => true]
        );

        $expected = [
            'trip_id' => 123,
            'day_count' => 2,
            'schedule' => [$day1, $day2],
            'cache_info' => ['cached' => true],
        ];

        $this->assertEquals($expected, $itinerary->jsonSerialize());
    }
}
