<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Core trip management functionality tests.
 *
 * This class verifies that:
 * - Trips can be created, read, updated, and deleted
 * - Trip ownership is properly managed
 * - Date validations are enforced
 * - Trip listing shows only relevant trips
 * - Unauthorized access is prevented
 */
#[Group('trip')]
#[Group('crud')]
class TripManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->trip = Trip::factory()->create(['owner_id' => $this->user->id]);
    }

    public static function invalidTripDataProvider(): array
    {
        return [
            'missing name' => [
                ['start_date' => '2024-01-01', 'end_date' => '2024-01-10'],
                'name',
            ],
            'name too long' => [
                ['name' => str_repeat('a', 256), 'start_date' => '2024-01-01', 'end_date' => '2024-01-10'],
                'name',
            ],
            'invalid start date format' => [
                ['name' => 'Invalid Date', 'start_date' => 'not-a-date', 'end_date' => '2024-01-10'],
                'start_date',
            ],
            'end date before start date' => [
                ['name' => 'Invalid Range', 'start_date' => '2024-01-10', 'end_date' => '2024-01-01'],
                'end_date',
            ],
        ];
    }

    public static function invalidLocationDataProvider(): array
    {
        return [
            'invalid latitude (too high)' => [100, 0, 'start_latitude'],
            'invalid latitude (too low)' => [-91, 0, 'start_latitude'],
            'invalid longitude (too high)' => [0, 181, 'start_longitude'],
            'invalid longitude (too low)' => [0, -181, 'start_longitude'],
            'non-numeric latitude' => ['invalid', 0, 'start_latitude'],
            'non-numeric longitude' => [0, 'invalid', 'start_longitude'],
        ];
    }

    public function test_authenticated_user_can_create_a_trip(): void
    {
        $tripData = [
            'name' => 'Test Trip',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-10',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'start_date',
                    'end_date',
                    'owner_id',
                ]
            ]);

        $this->assertDatabaseHas('trips', [
            'name' => 'Test Trip',
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_user_can_list_their_trips(): void
    {
        $user = User::factory()->create();

        Trip::factory()->count(3)->create(['owner_id' => $user->id]);

        Trip::factory()->create(['owner_id' => User::factory()->create()->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/trips');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_unauthorized_user_cannot_update_trip(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'name' => 'Hacked Trip',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_view_trip(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_delete_trip(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->deleteJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_view_their_trip_details(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $trip->id);
    }

    public function test_owner_can_update_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/trips/{$trip->id}", [
                'name' => 'Updated Trip Name',
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-10',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'name' => 'Updated Trip Name',
        ]);
    }

    public function test_owner_can_delete_their_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Trip deleted successfully'
            ]);

        $this->assertDatabaseMissing('trips', [
            'id' => $trip->id,
        ]);
    }

    public function test_trip_creation_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/trips', [
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-10',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/trips', [
                'name' => 'Invalid Date Range',
                'start_date' => '2024-01-10',
                'end_date' => '2024-01-01',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_owner_can_update_trip_location(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'owner_id' => $user->id,
            'start_latitude' => null,
            'start_longitude' => null,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/trips/{$trip->id}/start-location", [
                'start_latitude' => 51.1079,
                'start_longitude' => 17.0385,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Start location updated',
                'data' => [
                    'start_latitude' => '51.107900',
                    'start_longitude' => '17.038500',
                ]
            ]);

        $trip->refresh();
        $this->assertEquals('51.107900', $trip->start_latitude);
        $this->assertEquals('17.038500', $trip->start_longitude);
    }

    public function test_owner_can_list_trip_members(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        $trip->members()->attach($member->id, ['status' => 'accepted', 'role' => 'member']);

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/trips/{$trip->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data'); // Owner + 1 member
    }

    public function test_can_update_only_specific_fields(): void
    {
        $originalName = $this->trip->name;
        $originalStartDate = $this->trip->start_date->format('Y-m-d');
        $newEndDate = $this->trip->start_date->addDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'end_date' => $newEndDate,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('trips', [
            'id' => $this->trip->id,
            'name' => $originalName,
            'start_date' => $originalStartDate,
            'end_date' => $newEndDate,
        ]);
    }

    public function test_cannot_update_nonexistent_trip(): void
    {
        $nonExistentId = 9999;

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/trips/{$nonExistentId}", [
                'name' => 'Non-existent Trip',
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_nonexistent_trip(): void
    {
        $nonExistentId = 9999;

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/trips/{$nonExistentId}");

        $response->assertStatus(404);
    }

    public function test_cannot_view_nonexistent_trip(): void
    {
        $nonExistentId = 9999;

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/trips/{$nonExistentId}");

        $response->assertStatus(404);
    }

    public function test_trips_are_paginated(): void
    {
        Trip::factory()->count(15)->create(['owner_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/trips');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'start_date',
                        'end_date',
                        'owner_id',
                    ]
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ]
            ]);
    }

    public function test_can_update_trip_with_same_data(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'name' => $this->trip->name,
                'start_date' => $this->trip->start_date->format('Y-m-d'),
                'end_date' => $this->trip->end_date->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $routes = [
            ['get', '/api/v1/trips'],
            ['post', '/api/v1/trips'],
            ['get', "/api/v1/trips/{$this->trip->id}"],
            ['put', "/api/v1/trips/{$this->trip->id}"],
            ['delete', "/api/v1/trips/{$this->trip->id}"],
            ['get', "/api/v1/trips/{$this->trip->id}/members"],
            ['patch', "/api/v1/trips/{$this->trip->id}/start-location"],
        ];

        foreach ($routes as $route) {
            [$method, $uri] = $route;
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }
}
