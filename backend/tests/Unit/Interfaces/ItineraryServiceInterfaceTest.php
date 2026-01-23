<?php

namespace Tests\Unit\Interfaces;

use App\DTO\Itinerary\Itinerary;
use App\Interfaces\ItineraryServiceInterface;
use App\Models\Trip;
use Mockery;
use Tests\TestCase;

class ItineraryServiceInterfaceTest extends TestCase
{
    private ItineraryServiceInterface $itineraryService;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->itineraryService = Mockery::mock(ItineraryServiceInterface::class);
        $this->trip = Trip::factory()->make(['id' => 1]);
    }

    public function test_generate_returns_itinerary()
    {
        $expectedItinerary = new Itinerary(
            trip_id: $this->trip->id,
            day_count: 1,
            schedule: [],
            cache_info: null
        );
        
        $this->itineraryService->shouldReceive('generate')
            ->once()
            ->with($this->trip)
            ->andReturn($expectedItinerary);
            
        $result = $this->itineraryService->generate($this->trip);
        
        $this->assertInstanceOf(Itinerary::class, $result);
        $this->assertEquals($this->trip->id, $result->trip_id);
    }

    public function test_generate_full_route_returns_itinerary()
    {
        $days = 5;
        $radius = 10000;
        $expectedItinerary = new Itinerary(
            trip_id: $this->trip->id,
            day_count: $days,
            schedule: [],
            cache_info: ['cached' => false]
        );
        
        $this->itineraryService->shouldReceive('generateFullRoute')
            ->once()
            ->with($this->trip, $days, $radius)
            ->andReturn($expectedItinerary);
            
        $result = $this->itineraryService->generateFullRoute($this->trip, $days, $radius);
        
        $this->assertInstanceOf(Itinerary::class, $result);
        $this->assertEquals($days, $result->day_count);
    }

    public function test_aggregate_preferences_returns_array()
    {
        $expectedPreferences = [
            'museum' => 1.8,
            'food' => 2.0,
            'nature' => 0.5
        ];
        
        $this->itineraryService->shouldReceive('aggregatePreferences')
            ->once()
            ->with($this->trip)
            ->andReturn($expectedPreferences);
            
        $result = $this->itineraryService->aggregatePreferences($this->trip);
        
        $this->assertSame($expectedPreferences, $result);
    }
}
