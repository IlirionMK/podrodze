<?php

namespace Tests\Unit\Interfaces;

use App\DTO\Trip\TripPlace;
use App\DTO\Trip\TripVote;
use App\Interfaces\PlaceInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class PlaceInterfaceTest extends TestCase
{
    private PlaceInterface $placeService;
    private Trip $trip;
    private User $user;
    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->placeService = Mockery::mock(PlaceInterface::class);
        $this->trip = Trip::factory()->make();
        $this->user = User::factory()->make();
        $this->place = Place::factory()->make();
    }

    public function test_list_for_trip_returns_collection()
    {
        $tripPlace = new TripPlace(
            id: 1,
            place: [
                'id' => 1,
                'name' => 'Test Place',
                'category_slug' => 'restaurant',
                'lat' => 52.2297,
                'lon' => 21.0122
            ],
            status: 'suggested',
            is_fixed: false,
            day: 1,
            order_index: 1,
            note: 'Test note',
            added_by: 1
        );
        
        $collection = new Collection([$tripPlace]);
        
        $this->placeService->shouldReceive('listForTrip')
            ->once()
            ->with($this->trip)
            ->andReturn($collection);
            
        $result = $this->placeService->listForTrip($this->trip);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TripPlace::class, $result->first());
    }

    public function test_list_trip_votes_returns_collection()
    {
        $tripVote = new TripVote(
            place_id: 1,
            my_score: 5,
            avg_score: 4.5,
            votes: 10
        );
        
        $collection = new Collection([$tripVote]);
        
        $this->placeService->shouldReceive('listTripVotes')
            ->once()
            ->with($this->trip, $this->user)
            ->andReturn($collection);
            
        $result = $this->placeService->listTripVotes($this->trip, $this->user);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TripVote::class, $result->first());
    }

    public function test_create_custom_place()
    {
        $data = ['name' => 'Custom Place', 'lat' => 52.2297, 'lon' => 21.0122];
        $place = new Place($data);
        
        $this->placeService->shouldReceive('createCustomPlace')
            ->once()
            ->with($data, $this->user)
            ->andReturn($place);
            
        $result = $this->placeService->createCustomPlace($data, $this->user);
        
        $this->assertInstanceOf(Place::class, $result);
        $this->assertEquals('Custom Place', $result->name);
    }

    public function test_find_nearby_places()
    {
        $collection = new Collection([$this->place]);
        $lat = 52.2297;
        $lon = 21.0122;
        $radius = 1000;
        
        $this->placeService->shouldReceive('findNearby')
            ->once()
            ->with($lat, $lon, $radius)
            ->andReturn($collection);
            
        $result = $this->placeService->findNearby($lat, $lon, $radius);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    public function test_google_autocomplete()
    {
        $query = 'restaurant';
        $lat = 52.2297;
        $lon = 21.0122;
        $expected = [['place_id' => '123', 'description' => 'Restaurant Test']];
        
        // Update the mock to match the actual method signature
        $this->placeService->shouldReceive('googleAutocomplete')
            ->once()
            ->with($query, $lat, $lon, 2000, 'pl', null)
            ->andReturn($expected);
            
        // Call with all required parameters
        $result = $this->placeService->googleAutocomplete($query, $lat, $lon, 2000, 'pl', null);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('place_id', $result[0]);
    }

    public function test_google_details()
    {
        $placeId = 'ChIJL6wn6oDxOkcRZHa4TwwBgyE';
        $expected = ['place_id' => $placeId, 'name' => 'Test Place'];
        
        // Update the mock to match the actual method signature
        $this->placeService->shouldReceive('googleDetails')
            ->once()
            ->with($placeId, 'pl', null)
            ->andReturn($expected);
            
        // Call with all required parameters
        $result = $this->placeService->googleDetails($placeId, 'pl', null);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('place_id', $result);
        $this->assertEquals($placeId, $result['place_id']);
    }
}
