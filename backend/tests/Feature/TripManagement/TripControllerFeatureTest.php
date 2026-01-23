<?php

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Hash;

/**
 * Test suite for TripController API endpoints.
 *
 * This test verifies the functionality of trip management including:
 * 1. Listing user trips with pagination
 * 2. Creating new trips with validation
 * 3. Retrieving specific trip details
 * 4. Updating trip information
 * 5. Deleting trips
 * 6. Updating trip start location
 * 7. Authorization and access control
 * 8. Authentication requirements
 * 9. Error handling and validation
 *
 * @covers \App\Http\Controllers\Api\V1\TripController
 * @covers \App\Http\Resources\TripResource
 * @covers \App\Policies\TripPolicy
 */
#[Group('trip')]
#[Group('crud')]
#[Group('api')]
#[Group('feature')]
class TripControllerFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var User The authenticated test user */
    protected User $user;

    /** @var User Another user for authorization tests */
    protected User $otherUser;

    /** @var Trip The test trip instance */
    protected Trip $trip;

    /**
     * Set up the test environment.
     * Creates test users and trips for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->trip = Trip::factory()->create([
            'name' => 'Test Trip',
            'description' => 'A test trip for automated testing',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,  // Warsaw coordinates
            'start_longitude' => 21.0122,
        ]);

        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function it_lists_user_trips()
    {
        // Create additional trips for the user
        Trip::factory()->count(3)->create(['owner_id' => $this->user->id]);
        
        // Create trips for other user (should not appear)
        Trip::factory()->count(2)->create(['owner_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/v1/trips');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'start_date',
                        'end_date',
                        'start_latitude',
                        'start_longitude',
                        'owner_id',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'links',
                'meta'
            ]);

        // Should only return user's own trips (4 total: 1 from setUp + 3 new)
        $responseData = $response->json();
        $this->assertEquals(4, $responseData['meta']['total']);
    }

    #[Test]
    public function it_creates_a_new_trip()
    {
        $tripData = [
            'name' => 'New Test Trip',
            'description' => 'A newly created test trip',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'start_date',
                    'end_date',
                    'start_latitude',
                    'start_longitude',
                    'owner_id'
                ]
            ])
            ->assertJson([
                'message' => 'Trip created successfully',
                'data' => [
                    'name' => 'New Test Trip',
                    'description' => 'A newly created test trip',
                ]
            ]);

        $this->assertDatabaseHas('trips', [
            'name' => 'New Test Trip',
            'description' => 'A newly created test trip',
            'owner_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_validates_trip_creation_data()
    {
        $response = $this->postJson('/api/v1/trips', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_shows_a_specific_trip()
    {
        $response = $this->getJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'start_date',
                    'end_date',
                    'start_latitude',
                    'start_longitude',
                    'owner_id',
                    'owner',
                    'members'
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $this->trip->id,
                    'name' => 'Test Trip',
                    'description' => 'A test trip for automated testing',
                ]
            ]);
    }

    #[Test]
    public function it_prevents_showing_trip_to_unauthorized_user()
    {
        $response = $this->actingAs($this->otherUser)
            ->getJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_updates_a_trip()
    {
        $updateData = [
            'name' => 'Updated Trip Name',
            'description' => 'Updated description',
            'start_date' => now()->addDays(2)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
        ];

        $response = $this->putJson("/api/v1/trips/{$this->trip->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'start_latitude',
                    'start_longitude'
                ]
            ])
            ->assertJson([
                'message' => 'Trip updated successfully',
                'data' => [
                    'name' => 'Updated Trip Name',
                    'description' => 'Updated description',
                ]
            ]);

        $this->assertDatabaseHas('trips', [
            'id' => $this->trip->id,
            'name' => 'Updated Trip Name',
            'description' => 'Updated description',
        ]);
    }

    #[Test]
    public function it_prevents_updating_trip_by_unauthorized_user()
    {
        $updateData = [
            'name' => 'Unauthorized Update',
        ];

        $response = $this->actingAs($this->otherUser)
            ->putJson("/api/v1/trips/{$this->trip->id}", $updateData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_deletes_a_trip()
    {
        $response = $this->deleteJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Trip deleted successfully'
            ]);

        $this->assertDatabaseMissing('trips', [
            'id' => $this->trip->id,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_trip_by_unauthorized_user()
    {
        $response = $this->actingAs($this->otherUser)
            ->deleteJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('trips', [
            'id' => $this->trip->id,
        ]);
    }

    #[Test]
    public function it_updates_trip_start_location()
    {
        $locationData = [
            'start_latitude' => 51.21,
            'start_longitude' => 16.16,
        ];

        $response = $this->patchJson("/api/v1/trips/{$this->trip->id}/start-location", $locationData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'start_latitude',
                    'start_longitude'
                ]
            ])
            ->assertJson([
                'message' => 'Start location updated',
                'data' => [
                    'start_latitude' => 51.21,
                    'start_longitude' => 16.16,
                ]
            ]);

        $this->assertDatabaseHas('trips', [
            'id' => $this->trip->id,
            'start_latitude' => 51.21,
            'start_longitude' => 16.16,
        ]);
    }

    #[Test]
    public function it_validates_start_location_data()
    {
        $response = $this->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
            'start_latitude' => 'invalid',
            'start_longitude' => null,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_latitude', 'start_longitude']);
    }

    #[Test]
    public function it_validates_latitude_bounds()
    {
        $response = $this->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
            'start_latitude' => 91.0,  // Above 90
            'start_longitude' => 16.16,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_latitude']);

        $response = $this->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
            'start_latitude' => -91.0,  // Below -90
            'start_longitude' => 16.16,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_latitude']);
    }

    #[Test]
    public function it_validates_longitude_bounds()
    {
        $response = $this->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
            'start_latitude' => 52.2297,
            'start_longitude' => 181.0,  // Above 180
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_longitude']);

        $response = $this->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
            'start_latitude' => 52.2297,
            'start_longitude' => -181.0,  // Below -180
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_longitude']);
    }

    #[Test]
    public function it_prevents_updating_location_by_unauthorized_user()
    {
        $locationData = [
            'start_latitude' => 51.21,
            'start_longitude' => 16.16,
        ];

        $response = $this->actingAs($this->otherUser)
            ->patchJson("/api/v1/trips/{$this->trip->id}/start-location", $locationData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_trip()
    {
        $nonExistentId = 99999;

        $response = $this->getJson("/api/v1/trips/{$nonExistentId}");

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_when_updating_nonexistent_trip()
    {
        $nonExistentId = 99999;
        $updateData = ['name' => 'Updated Name'];

        $response = $this->putJson("/api/v1/trips/{$nonExistentId}", $updateData);

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_when_deleting_nonexistent_trip()
    {
        $nonExistentId = 99999;

        $response = $this->deleteJson("/api/v1/trips/{$nonExistentId}");

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_when_updating_location_of_nonexistent_trip()
    {
        $nonExistentId = 99999;
        $locationData = [
            'start_latitude' => 51.21,
            'start_longitude' => 16.16,
        ];

        $response = $this->patchJson("/api/v1/trips/{$nonExistentId}/start-location", $locationData);

        $response->assertStatus(404);
    }

    #[Test]
    public function it_requires_authentication_for_all_endpoints()
    {
        $this->refreshApplication();

        // Test all endpoints without authentication
        $this->getJson('/api/v1/trips')->assertStatus(401);
        $this->postJson('/api/v1/trips', [])->assertStatus(401);
        $this->getJson("/api/v1/trips/{$this->trip->id}")->assertStatus(401);
        $this->putJson("/api/v1/trips/{$this->trip->id}", [])->assertStatus(401);
        $this->deleteJson("/api/v1/trips/{$this->trip->id}")->assertStatus(401);
        $this->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [])->assertStatus(401);
    }

    #[Test]
    public function it_validates_trip_dates()
    {
        // Test end date before start date
        $invalidTripData = [
            'name' => 'Invalid Date Trip',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/trips', $invalidTripData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function it_handles_trip_with_optional_fields()
    {
        $minimalTripData = [
            'name' => 'Minimal Trip',
        ];

        $response = $this->postJson('/api/v1/trips', $minimalTripData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Trip created successfully',
                'data' => [
                    'name' => 'Minimal Trip',
                ]
            ]);

        $this->assertDatabaseHas('trips', [
            'name' => 'Minimal Trip',
            'description' => null,
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    #[Test]
    public function it_allows_trip_members_to_view_trip()
    {
        // Add other user as a member
        $this->trip->members()->attach($this->otherUser->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $response = $this->actingAs($this->otherUser)
            ->getJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->trip->id,
                    'name' => 'Test Trip',
                ]
            ]);
    }

    #[Test]
    public function it_prevents_trip_members_from_updating_trip()
    {
        // Add other user as a member
        $this->trip->members()->attach($this->otherUser->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $updateData = [
            'name' => 'Unauthorized Member Update',
        ];

        $response = $this->actingAs($this->otherUser)
            ->putJson("/api/v1/trips/{$this->trip->id}", $updateData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_prevents_trip_members_from_deleting_trip()
    {
        // Add other user as a member
        $this->trip->members()->attach($this->otherUser->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $response = $this->actingAs($this->otherUser)
            ->deleteJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);
    }
}
