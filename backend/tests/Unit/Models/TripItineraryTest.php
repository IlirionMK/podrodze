<?php

namespace Tests\Unit\Models;

use App\Models\Trip;
use App\Models\TripItinerary;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\ModelTestCase;

class TripItineraryTest extends ModelTestCase
{
    #[Test]
    public function it_has_required_fields()
    {
        $trip = $this->createTrip();
        $schedule = [
            'days' => [
                [
                    'date' => now()->format('Y-m-d'),
                    'activities' => [
                        [
                            'time' => '09:00',
                            'description' => 'Test activity',
                            'place_id' => null,
                        ]
                    ]
                ]
            ]
        ];

        $itinerary = $this->createTripItinerary([
            'trip_id' => $trip->id,
            'schedule' => $schedule,
            'day_count' => 1,
        ]);

        $this->assertEquals($trip->id, $itinerary->trip_id);
        $this->assertEquals($schedule, $itinerary->schedule);
        $this->assertEquals(1, $itinerary->day_count);
        $this->assertNotNull($itinerary->generated_at);
    }

    #[Test]
    public function it_belongs_to_trip()
    {
        $trip = $this->createTrip();
        $itinerary = $this->createTripItinerary(['trip_id' => $trip->id]);

        $this->assertEquals($trip->id, $itinerary->trip_id);
        $this->assertInstanceOf(Trip::class, $itinerary->trip);
    }

    #[Test]
    public function it_casts_schedule_as_array()
    {
        $schedule = ['days' => [['date' => '2023-01-01', 'activities' => []]]];
        $itinerary = $this->createTripItinerary(['schedule' => $schedule]);

        $this->assertIsArray($itinerary->schedule);
        $this->assertEquals($schedule, $itinerary->schedule);
    }
}
