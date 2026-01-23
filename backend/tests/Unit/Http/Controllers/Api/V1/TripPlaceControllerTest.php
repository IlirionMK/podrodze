<?php

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\TripPlaceController;
use App\Http\Requests\TripPlaceStoreRequest;
use App\Http\Requests\TripPlaceUpdateRequest;
use App\Http\Requests\TripPlaceVoteRequest;
use App\Interfaces\PlaceInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use App\DTO\Trip\TripPlace;
use App\DTO\Trip\TripVote;
use App\DTO\Trip\TripPlaceVoteSummary;
use App\Services\External\GooglePlacesService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;


class TripPlaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private $placeService;
    private $controller;
    private $user;
    private $trip;
    private $tripPlace;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock authorization to bypass all checks
        $this->mock(\Illuminate\Foundation\Auth\Access\Gate::class, function ($mock) {
            $mock->shouldReceive('authorize')->andReturn(true);
            $mock->shouldReceive('check')->andReturn(true);
            $mock->shouldReceive('allows')->andReturn(true);
            $mock->shouldReceive('denies')->andReturn(false);
        });

        $this->placeService = Mockery::mock(PlaceInterface::class);
        $this->controller = new TripPlaceController($this->placeService);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->trip = Trip::factory()->create(['owner_id' => $this->user->id]);

        // Create a place with only the fields that exist in the database
        $this->place = Place::create([
            'name' => 'Test Place',
            'category_slug' => 'test',
            'rating' => 4.5,
            'meta' => [
                'address' => '123 Test St',
                'lat' => 52.2297,
                'lng' => 21.0122,
                'status' => 'proposed'
            ],
            // Using raw SQL to set the location as a PostGIS point
            'location' => DB::raw("ST_GeomFromText('POINT(21.0122 52.2297)', 4326)")
        ]);

        $this->trip->places()->attach($this->place->id, ['status' => 'proposed']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_trip_places()
    {
        $expectedPlaces = collect([
            (object)[
                'id' => $this->place->id,
                'place' => [
                    'id' => $this->place->id,
                    'name' => $this->place->name,
                    'category_slug' => $this->place->category_slug,
                    'lat' => 52.2297,
                    'lon' => 21.0122,
                ],
                'status' => 'proposed',
                'is_fixed' => false,
                'day' => null,
                'order_index' => 0,
                'note' => null,
                'added_by' => $this->user->id
            ]
        ]);

        $this->placeService->shouldReceive('listForTrip')
            ->with($this->trip)
            ->once()
            ->andReturn($expectedPlaces);

        $response = $this->controller->index($this->trip);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());
        $responseData = json_decode($response->content(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data']);
        $this->assertEquals($this->place->name, $responseData['data'][0]['place']['name']);
    }

    #[Test]
    public function it_stores_a_trip_place()
    {
        $data = [
            'name' => 'New Place',
            'category_slug' => 'restaurant',
            'meta' => [
                'address' => '123 Test St',
                'lat' => 12.3456,
                'lng' => 65.4321,
                'status' => 'proposed'
            ]
        ];

        $request = Mockery::mock(TripPlaceStoreRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($data);
        $request->shouldReceive('user')->once()->andReturn($this->user);

        $this->placeService->shouldReceive('addToTrip')
            ->with($this->trip, $data, $this->user)
            ->once()
            ->andReturn(new TripPlace(
                id: 1,
                place: [
                    'id' => $this->place->id,
                    'name' => $data['name'],
                    'category_slug' => $data['category_slug'],
                    'lat' => 12.3456,
                    'lon' => 65.4321,
                ],
                status: 'proposed',
                is_fixed: false,
                day: null,
                order_index: 0,
                note: null,
                added_by: $this->user->id
            ));

        $response = $this->controller->store($request, $this->trip);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->status());
        $this->assertJson($response->content());
        $responseData = json_decode($response->content(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals($data['name'], $responseData['data']['place']['name']);
    }

    #[Test]
    public function it_updates_a_trip_place()
    {
        $data = [
            'name' => 'Updated Place',
            'category_slug' => 'restaurant',
            'meta' => [
                'address' => '456 Updated St',
                'lat' => 12.3456,
                'lng' => 65.4321,
                'status' => 'confirmed'
            ]
        ];

        $request = Mockery::mock(TripPlaceUpdateRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($data);

        $updatedPlace = new TripPlace(
            id: $this->place->id,
            place: [
                'id' => $this->place->id,
                'name' => $data['name'],
                'category_slug' => $data['category_slug'],
                'lat' => 12.3456,
                'lon' => 65.4321,
            ],
            status: 'confirmed',
            is_fixed: false,
            day: null,
            order_index: 0,
            note: null,
            added_by: $this->user->id
        );

        $this->placeService->shouldReceive('updateTripPlace')
            ->with($this->trip, $this->place, $data)
            ->once()
            ->andReturn($updatedPlace);

        $response = $this->controller->update($request, $this->trip, $this->place);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());
        $responseData = json_decode($response->content(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals($data['name'], $responseData['data']['place']['name']);
    }

    #[Test]
    public function it_votes_on_a_trip_place()
    {
        $data = ['score' => 5];

        $request = Mockery::mock(TripPlaceVoteRequest::class);
        $request->shouldReceive('validated')->once()->andReturn(['score' => 5]);
        $request->shouldReceive('user')->once()->andReturn($this->user);

        $voteResult = new TripVote(
            place_id: $this->place->id,
            my_score: 5,
            avg_score: 5.0,
            votes: 1
        );

        $this->placeService->shouldReceive('saveTripVote')
            ->with($this->trip, $this->place, $this->user, 5)
            ->once()
            ->andReturn($voteResult);

        $response = $this->controller->vote($request, $this->trip, $this->place);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());
        $responseData = json_decode($response->content(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals(5, $responseData['data']['my_score']);
    }

    #[Test]
    public function it_removes_a_trip_place()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->user);

        $this->placeService->shouldReceive('detachFromTrip')
            ->with($this->trip, $this->place, $this->user)
            ->once();

        $response = $this->controller->destroy($request, $this->trip, $this->place);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());
        $responseData = json_decode($response->content(), true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Place removed from trip', $responseData['message']);
    }

    #[Test]
    public function it_returns_nearby_google_places_for_trip()
    {
        $trip = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,
            'start_longitude' => 21.0122,
        ]);

        $googleService = Mockery::mock(GooglePlacesService::class);
        $googleService->shouldReceive('fetchNearbyForTrip')
            ->once()
            ->with($trip, 1000, 20)
            ->andReturn([
                (object)[
                    'place_id' => 'test_place_id',
                    'name' => 'Test Place',
                    'vicinity' => 'Test Address',
                    'geometry' => (object)[
                        'location' => (object)['lat' => 52.2297, 'lng' => 21.0122]
                    ],
                    'types' => ['restaurant']
                ]
            ]);

        $request = new Request([
            'keyword' => 'restaurant',
            'radius' => 1000,
        ]);

        $response = $this->controller->nearbyGoogle($request, $trip, $googleService);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());
        $responseData = json_decode($response->content(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data']);
        $this->assertEquals('Test Place', $responseData['data'][0]['name']);
    }

    #[Test]
    public function it_handles_nearby_google_without_trip_location()
    {
        $trip = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => null,
            'start_longitude' => null,
        ]);

        $googleService = Mockery::mock(GooglePlacesService::class);
        $googleService->shouldNotReceive('fetchNearbyForTrip');

        $request = new Request([
            'keyword' => 'restaurant',
            'radius' => 1000,
        ]);

        $response = $this->controller->nearbyGoogle($request, $trip, $googleService);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->status());
        $this->assertJson($response->content());
        $responseData = json_decode($response->content(), true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Trip has no starting location defined.', $responseData['message']);
    }

    #[Test]
    public function it_returns_trip_place_votes()
    {
        $votes = collect([
            new TripPlaceVoteSummary(
                place_id: $this->place->id,
                avg_score: 5.0,
                votes: 1,
                my_score: 5
            )
        ]);

        $this->placeService->shouldReceive('listTripVotes')
            ->with($this->trip, $this->user)
            ->once()
            ->andReturn($votes);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->user);

        $response = $this->controller->votes($request, $this->trip);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());
        $responseData = json_decode($response->content(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data']);
        $this->assertEquals(5, $responseData['data'][0]['my_score']);
    }
}
