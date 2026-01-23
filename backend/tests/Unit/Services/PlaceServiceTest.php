<?php

namespace Tests\Unit\Services;

use App\DTO\Trip\TripPlace;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\External\GooglePlacesService;
use App\Services\PlaceService;
use App\Services\PlacesSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase as BaseTestCase;

class PlaceServiceTest extends \Tests\TestCase
{
    use RefreshDatabase;

    private PlaceService $service;
    private MockObject|GooglePlacesService $googlePlaces;
    private MockObject|PlacesSyncService $placesSync;
    private MockObject|ActivityLogger $activityLogger;
    private User $user;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->googlePlaces = $this->createMock(GooglePlacesService::class);
        $this->placesSync = $this->createMock(PlacesSyncService::class);
        $this->activityLogger = $this->createMock(ActivityLogger::class);
        
        // Create service with mocked dependencies
        $this->service = new PlaceService(
            $this->activityLogger,
            $this->googlePlaces,
            $this->placesSync
        );
        
        // Create test data
        $this->user = User::factory()->create();
        $this->trip = Trip::factory()->create(['owner_id' => $this->user->id]);
    }

    #[Test]
    public function it_gets_google_autocomplete_suggestions()
    {
        $query = 'test';
        $expected = [
            ['place_id' => '123', 'description' => 'Test Place']
        ];
        
        $this->googlePlaces->expects($this->once())
            ->method('autocomplete')
            ->with($query, null, null, null, 'pl', null)
            ->willReturn($expected);
            
        $result = $this->service->googleAutocomplete($query);
        
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_gets_google_place_details()
    {
        $placeId = 'test-place-id';
        $expected = [
            'place_id' => $placeId,
            'name' => 'Test Place',
            'geometry' => ['location' => ['lat' => 52.23, 'lng' => 21.01]]
        ];
        
        $this->googlePlaces->expects($this->once())
            ->method('getPlaceDetails')
            ->with($placeId, 'pl', null)
            ->willReturn($expected);
            
        $result = $this->service->googleDetails($placeId);
        
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_finds_nearby_places_and_syncs()
    {
        $lat = 52.23;
        $lon = 21.01;
        $radius = 2000;
        
        $syncResult = ['synced' => 5, 'total' => 5];
        $places = [
            ['id' => 1, 'name' => 'Place 1'],
            ['id' => 2, 'name' => 'Place 2']
        ];
        
        $this->placesSync->expects($this->once())
            ->method('fetchAndStore')
            ->with($lat, $lon, $radius)
            ->willReturn($syncResult);
            
        // Mock the actual database query
        Place::factory()->create([
            'location' => DB::raw("ST_SetSRID(ST_MakePoint($lon, $lat), 4326)")
        ]);
        
        $result = $this->service->nearbyWithSync($lat, $lon, $radius);
        
        $this->assertEquals($syncResult, $result['summary']);
        $this->assertCount(1, $result['places']);
    }

    #[Test]
    public function it_lists_places_for_trip()
    {
        $place = Place::factory()->create();
        $this->trip->places()->attach($place->id, [
            'status' => 'planned',
            'is_fixed' => true,
            'day' => 1,
            'order_index' => 1,
            'added_by' => $this->user->id
        ]);
        
        $result = $this->service->listForTrip($this->trip);
        
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TripPlace::class, $result->first());
        $this->assertEquals($place->id, $result->first()->id);
    }

    #[Test]
    public function it_creates_custom_place()
    {
        $data = [
            'name' => 'Custom Place',
            'category' => 'attraction',
            'lat' => 52.23,
            'lon' => 21.01,
            'opening_hours' => ['monday' => '9:00-17:00']
        ];
        
        $place = $this->service->createCustomPlace($data, $this->user);
        
        $this->assertDatabaseHas('places', [
            'name' => 'Custom Place',
            'category_slug' => 'attraction',
            'meta->source' => 'custom'
        ]);
        $this->assertEquals($this->user->id, $place->meta['created_by']);
    }

    #[Test]
    public function it_adds_place_to_trip()
    {
        $place = Place::factory()->create();
        $data = [
            'place_id' => $place->id,
            'status' => 'planned',
            'day' => 1,
            'order_index' => 1,
            'note' => 'Test note'
        ];
        
        $result = $this->service->addToTrip($this->trip, $data, $this->user);
        
        $this->assertInstanceOf(TripPlace::class, $result);
        $this->assertDatabaseHas('trip_place', [
            'trip_id' => $this->trip->id,
            'place_id' => $place->id,
            'status' => 'planned',
            'day' => 1,
            'order_index' => 1,
            'note' => 'Test note',
            'added_by' => $this->user->id
        ]);
    }
    
    #[Test]
    public function it_prevents_duplicate_places_in_trip()
    {
        $place = Place::factory()->create();
        $this->trip->places()->attach($place->id, ['added_by' => $this->user->id]);
        
        $this->expectException(\DomainException::class);
        
        $this->service->addToTrip($this->trip, ['place_id' => $place->id], $this->user);
    }
    
    #[Test]
    public function it_resolves_google_place()
    {
        $googlePlaceId = 'test-google-id';
        $placeData = [
            'place_id' => $googlePlaceId,
            'name' => 'Google Place',
            'types' => ['restaurant'],
            'lat' => 52.23,
            'lon' => 21.01,
            'rating' => 4.5
        ];
        
        $this->googlePlaces->expects($this->once())
            ->method('getPlaceDetails')
            ->with($googlePlaceId, 'pl')
            ->willReturn($placeData);
            
        $this->service->addToTrip($this->trip, ['google_place_id' => $googlePlaceId], $this->user);
        $place = Place::query()->where('google_place_id', $googlePlaceId)->firstOrFail();
        
        $this->assertEquals('Google Place', $place->name);
        $this->assertEquals('food', $place->category_slug);
        $this->assertEquals(4.5, $place->rating);
    }
}
