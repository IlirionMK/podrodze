<?php

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\TripTestCase;

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
class TripManagementTest extends TripTestCase
{
    protected bool $enableRateLimiting = false;

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
            'start_date' => now()->addDays(0)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->actingAsUser($this->owner)
            ->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'start_date',
                    'end_date',
                ]
            ]);

        $this->assertDatabaseHas('trips', [
            'name' => 'Test Trip',
            'owner_id' => $this->owner->getKey(),
        ]);
    }

    public function test_user_can_list_their_trips(): void
    {
        Trip::factory()->count(2)->create(['owner_id' => $this->owner->getKey()]);

        Trip::factory()->create(['owner_id' => $this->otherUser->getKey()]);

        $response = $this->actingAsUser($this->owner)
            ->getJson('/api/v1/trips');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data'); // 1 from setUp + 2 just created
    }

    public function test_unauthorized_user_cannot_update_trip(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAsUser($otherUser)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'name' => 'Hacked Trip',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_view_trip(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAsUser($otherUser)
            ->getJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_delete_trip(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAsUser($otherUser)
            ->deleteJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_view_their_trip_details(): void
    {
        $response = $this->actingAsUser($this->owner)
            ->getJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->trip->id);
    }

    public function test_owner_can_update_trip(): void
    {
        $response = $this->actingAsUser($this->owner)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'name' => 'Updated Trip Name',
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-10',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Trip Name');

        $this->assertDatabaseHas('trips', [
            'id' => $this->trip->id,
            'name' => 'Updated Trip Name',
        ]);
    }

    public function test_owner_can_delete_their_trip(): void
    {
        $trip = $this->createTrip(['owner_id' => $this->owner->getKey()]);

        $response = $this->actingAsUser($this->owner)
            ->deleteJson("/api/v1/trips/{$trip->getKey()}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Trip deleted successfully']);

        $tripExists = \DB::table('trips')
            ->where('id', $trip->id)
            ->when(in_array('deleted_at', \Schema::getColumnListing('trips')),
                fn($query) => $query->whereNotNull('deleted_at'),
                fn($query) => $query
            )->exists();

        $this->assertFalse($tripExists, 'The trip was not deleted properly');
    }

    public function test_trip_creation_requires_name(): void
    {
        $response = $this->actingAsUser($this->owner)
            ->postJson('/api/v1/trips', [
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-10',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $response = $this->actingAsUser($this->owner)
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
        $trip = $this->createTrip([
            'owner_id' => $this->owner->getKey(),
            'start_latitude' => null,
            'start_longitude' => null,
        ]);

        $response = $this->actingAsUser($this->owner)
            ->patchJson("/api/v1/trips/$trip->id/start-location", [
                'start_latitude' => 51.1079,
                'start_longitude' => 17.0385,
            ]);

        $response->assertStatus(200);

        // Check the response with a delta to handle floating-point precision
        $responseData = $response->json('data');
        $this->assertEqualsWithDelta(51.1079, $responseData['start_latitude'], 0.0001, 'Latitude does not match expected value');
        $this->assertEqualsWithDelta(17.0385, $responseData['start_longitude'], 0.0001, 'Longitude does not match expected value');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'start_latitude' => 51.1079,
            'start_longitude' => 17.0385,
        ]);
    }

    public function test_owner_can_list_trip_members(): void
    {
        $trip = $this->createTrip(['owner_id' => $this->owner->getKey()]);
        $trip->members()->attach($this->member->getKey(), ['status' => 'accepted', 'role' => 'member']);

        $response = $this->actingAsUser($this->owner)
            ->getJson("/api/v1/trips/$trip->id/members");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data'); // Owner + 1 member
    }

    public function test_can_update_only_specific_fields(): void
    {
        $originalName = $this->trip->name;
        $originalStartDate = $this->trip->start_date->format('Y-m-d');
        $newEndDate = $this->trip->start_date->addDays(5)->format('Y-m-d');

        $response = $this->actingAsUser($this->owner)
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

        $response = $this->actingAsUser($this->owner)
            ->putJson("/api/v1/trips/$nonExistentId", [
                'name' => 'Non-existent Trip',
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_nonexistent_trip(): void
    {
        $nonExistentId = 9999;

        $response = $this->actingAsUser($this->owner)
            ->deleteJson("/api/v1/trips/$nonExistentId");

        $response->assertStatus(404);
    }

    public function test_cannot_view_nonexistent_trip(): void
    {
        $nonExistentId = 9999;

        $response = $this->actingAsUser($this->owner)
            ->getJson("/api/v1/trips/$nonExistentId");

        $response->assertStatus(404);
    }

    public function test_trips_are_paginated(): void
    {
        Trip::factory()->count(14)->create(['owner_id' => $this->owner->getKey()]);

        $response = $this->actingAsUser($this->owner)
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
        $response = $this->actingAsUser($this->owner)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'name' => $this->trip->name,
                'start_date' => $this->trip->start_date->format('Y-m-d'),
                'end_date' => $this->trip->end_date->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
    }

    // W.I.P
    public function test_guest_cannot_access_protected_routes(): void
    {
        $nonExistentId = 999999;

        $routes = [
            ['get', '/api/v1/trips', 200],
            ['post', '/api/v1/trips', 422],
            ['get', "/api/v1/trips/$nonExistentId", 404],
            ['put', "/api/v1/trips/$nonExistentId", 404],
            ['delete', "/api/v1/trips/$nonExistentId", 404],
            ['get', "/api/v1/trips/$nonExistentId/members", 404],
            ['patch', "/api/v1/trips/$nonExistentId/start-location", 404],
        ];

        foreach ($routes as $route) {
            [$method, $uri, $expectedStatus] = $route;
            $response = $this->actingAsUser($this->owner)
                ->json($method, $uri);

            if ($response->status() !== $expectedStatus) {
                echo "\nRoute: $method $uri\n";
                echo "Expected status: $expectedStatus, got: {$response->status()}\n";
                echo "Response: " . $response->getContent() . "\n";
            }

            $response->assertStatus($expectedStatus);
        }
    }
}
