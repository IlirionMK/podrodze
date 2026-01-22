<?php

namespace Tests\Feature\TripManagement;

use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test suite for TripPlaceController API endpoints.
 *
 * This test verifies the functionality of trip place management, including:
 * 1. Adding/removing places to/from trips
 * 2. Managing place metadata within trips
 * 3. Retrieving places for specific trips
 * 4. Place ordering and categorization in trips
 * 5. Access control for trip places
 *
 * @covers \App\Http\Controllers\Trip\TripPlaceController
 * @covers \App\Models\TripPlace
 * @covers \App\Policies\TripPlacePolicy
 */
#[Group('trip')]
#[Group('place')]
#[Group('api')]
#[Group('feature')]
class TripPlaceControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @var User The authenticated test user */
    protected User $user;

    /** @var Trip The test trip instance */
    protected Trip $trip;

    /** @var Place The test place instance */
    protected Place $place;

    /**
     * Set up the test environment.
     * Creates a test user, trip, and place for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->trip = Trip::create([
            'name' => 'Test Trip',
            'description' => 'A test trip for automated testing',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,  // Warsaw coordinates
            'start_longitude' => 21.0122,
        ]);

        $this->place = Place::create([
            'name' => 'Test Place',
            'google_place_id' => 'test_place_' . uniqid(),
            'category_slug' => 'test-category',
            'rating' => 4.5,
            'meta' => ['address' => '123 Test St'],
            'opening_hours' => [
                'monday' => ['open' => '09:00', 'close' => '17:00'],
                'tuesday' => ['open' => '09:00', 'close' => '17:00'],
            ],
            'location' => DB::raw("ST_GeomFromText('POINT(52.2297 21.0122)')"),
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    #[Test]
    public function it_returns_nearby_places_for_trip()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/trips/{$this->trip->id}/places/nearby?radius=1000");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'address',
                        'rating',
                        'category_slug',
                    ]
                ]
            ]);
    }

    #[Test]
    public function it_adds_place_to_trip()
    {
        $newPlace = Place::create([
            'name' => 'New Test Place',
            'google_place_id' => 'new_test_place_456',
            'category_slug' => 'test-category',
            'rating' => 4.0,
            'meta' => ['address' => '456 New Test St'],
            'location' => DB::raw("ST_GeomFromText('POINT(52.2297 21.0122)')"),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places", [
                'place_id' => $newPlace->id,
                'notes' => 'Test note',
                'visit_date' => now()->addDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Place added to trip',
                'data' => [
                    'place' => [
                        'id' => $newPlace->id,
                        'name' => 'New Test Place',
                        'category_slug' => 'test-category',
                    ],
                    'status' => 'planned',
                    'is_fixed' => false,
                    'added_by' => $this->user->id,
                ]
            ]);

        $this->assertDatabaseHas('trip_place', [
            'trip_id' => $this->trip->id,
            'place_id' => $newPlace->id,
        ]);
    }

    #[Test]
    public function it_updates_place_in_trip()
    {
        $this->trip->places()->attach($this->place->id, [
            'note' => 'Original note',
            'day' => 1,
            'added_by' => $this->user->id,
        ]);

        $updateData = [
            'note' => 'Updated note',
            'day' => 2,
        ];

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Trip place updated',
                'data' => [
                    'place' => [
                        'id' => $this->place->id,
                        'name' => $this->place->name,
                        'category_slug' => $this->place->category_slug,
                    ],
                    'note' => 'Updated note',
                ]
            ]);

        $this->assertDatabaseHas('trip_place', [
            'trip_id' => $this->trip->id,
            'place_id' => $this->place->id,
            'note' => 'Updated note',
        ]);
    }

    #[Test]
    public function it_removes_place_from_trip()
    {
        $this->trip->places()->attach($this->place->id);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Place removed from trip',
            ]);

        $this->assertDatabaseMissing('trip_place', [
            'trip_id' => $this->trip->id,
            'place_id' => $this->place->id,
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_place_in_trip()
    {
        $this->trip->places()->attach($this->place->id);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places", [
                'place_id' => $this->place->id,
                'notes' => 'Test note',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'This place is already attached to the trip.',
            ]);
    }

    #[Test]
    public function it_requires_authentication()
    {
        $this->withHeaders(['Authorization' => '']);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/nearby");
        $response->assertStatus(401);

        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/places", []);
        $response->assertStatus(401);
    }
}
