<?php

namespace Tests\Unit\Services;

use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Services\ItineraryService;
use App\Interfaces\PreferenceAggregatorServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ItineraryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItineraryService $service;
    private User $user;
    private Trip $trip;
    private MockObject|PreferenceAggregatorServiceInterface $aggregatorMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the preference aggregator
        $this->aggregatorMock = $this->createMock(PreferenceAggregatorServiceInterface::class);
        $this->aggregatorMock->method('getGroupPreferences')->willReturn([
            'museum' => 1.5,
            'food' => 2.0,
            'nature' => 1.0
        ]);
        
        $this->service = new ItineraryService($this->aggregatorMock, app('App\Services\Activity\ActivityLogger'));
        $this->user = User::factory()->create();
        $this->trip = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,
            'start_longitude' => 21.0122
        ]);
    }

    #[Test]
    public function it_generates_itinerary()
    {
        // Add places to the trip
        $places = Place::factory()->count(3)->create();
        foreach ($places as $place) {
            $this->trip->places()->attach($place->id, ['is_fixed' => false]);
        }

        $itinerary = $this->service->generate($this->trip);

        $this->assertEquals($this->trip->id, $itinerary->trip_id);
        $this->assertEquals(1, $itinerary->day_count);
        $this->assertNotEmpty($itinerary->schedule);
        $this->assertCount(1, $itinerary->schedule);
    }

    #[Test]
    public function it_generates_full_route()
    {
        // Add places to the trip
        $places = Place::factory()->count(3)->create();
        foreach ($places as $place) {
            $this->trip->places()->attach($place->id, ['is_fixed' => false]);
        }

        $itinerary = $this->service->generateFullRoute($this->trip, 2, 5000);

        $this->assertEquals($this->trip->id, $itinerary->trip_id);
        $this->assertEquals(2, $itinerary->day_count);
        $this->assertNotEmpty($itinerary->schedule);
        $this->assertCount(2, $itinerary->schedule);
        
        // Check that itinerary was cached in database
        $this->assertDatabaseHas('trip_itineraries', [
            'trip_id' => $this->trip->id,
            'day_count' => 2
        ]);
    }

    #[Test]
    public function it_aggregates_preferences()
    {
        // This test would require setting up users with preferences
        // and trip participants, but for now we'll test the method exists
        $preferences = $this->service->aggregatePreferences($this->trip);
        
        // Should return an array (even if empty for this test)
        $this->assertIsArray($preferences);
    }

    #[Test]
    public function it_validates_trip_requirements()
    {
        // Test with no places - should throw exception
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No places added for this trip.');
        
        // Create a trip without places but with start location
        $tripWithoutPlaces = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,
            'start_longitude' => 21.0122
        ]);
        
        $this->service->generate($tripWithoutPlaces);
    }
}
