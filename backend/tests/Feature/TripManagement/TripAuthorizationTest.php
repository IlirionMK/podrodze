<?php

declare(strict_types=1);

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\TripTestCase;

/**
 * Tests for trip authorization and access control.
 *
 * This class verifies that:
 * - Unauthorized users cannot perform restricted actions on trips
 * - Proper HTTP status codes are returned for unauthorized access attempts
 * - Trip ownership is correctly enforced
 * - Role-based permissions are properly validated
 * - Guest access is restricted for protected routes
 */
#[Group('authorization')]
#[Group('trip')]
class TripAuthorizationTest extends TripTestCase
{
    protected bool $enableRateLimiting = false;

    protected User $editor;
    protected User $member;
    protected User $otherUser;
    protected User $owner;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = $this->createUser();
        $this->member = $this->createUser();
        $this->otherUser = $this->createUser();
        $this->owner = $this->createUser();

        $this->trip->members()->attach($this->editor->id, [
            'status' => 'accepted',
            'role' => 'editor'
        ]);

        $this->trip->members()->attach($this->member->id, [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $this->trip->members()->updateExistingPivot($this->owner->id, [
            'role' => 'owner'
        ]);
    }

    public function test_guest_access_to_routes(): void
    {
        $protectedRoutes = [
            ['get', '/api/v1/trips', 401],
            ['get', "/api/v1/trips/1", 401],
            ['post', '/api/v1/trips', 401],
            ['put', "/api/v1/trips/1", 401],
            ['delete', "/api/v1/trips/1", 401],
            ['get', "/api/v1/trips/1/members", 401],
            ['post', "/api/v1/trips/1/members/invite", 401],
            ['delete', "/api/v1/trips/1/members/1", 401],
        ];

        foreach ($protectedRoutes as $route) {
            [$method, $uri] = $route;
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }

        $publicRoutes = [
            ['get', '/api/v1/google/maps-key', 200],
        ];

        foreach ($publicRoutes as $route) {
            [$method, $uri, $expectedStatus] = $route;
            $response = $this->json($method, $uri);
            $response->assertStatus($expectedStatus);
        }

        $nonExistentId = 999999;
        $protectedRoutesWithNonExistentResources = [
            ['get', "/api/v1/trips/$nonExistentId", 401],
            ['put', "/api/v1/trips/$nonExistentId", 401],
            ['delete', "/api/v1/trips/$nonExistentId", 401],
            ['get', "/api/v1/trips/$nonExistentId/members", 401],
            ['patch', "/api/v1/trips/$nonExistentId/start-location", 401],
            ['post', "/api/v1/trips/$nonExistentId/members/invite", 401],
            ['delete', "/api/v1/trips/$nonExistentId/members/1", 401],
        ];

        foreach ($protectedRoutesWithNonExistentResources as $route) {
            [$method, $uri] = $route;
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    public function test_member_access(): void
    {
        $response = $this->actingAsUser($this->member)
            ->getJson("/api/v1/trips/{$this->trip->id}");
        $response->assertStatus(200);

        $response = $this->actingAsUser($this->member)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");
        $response->assertStatus(200);
    }

    public function test_owner_cannot_be_removed()
    {
        $response = $this->actingAsUser($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->id}");
        $response->assertStatus(403);

        $anotherOwner = User::factory()->create();
        $response = $this->actingAsUser($anotherOwner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->id}");
        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_update_trip(): void
    {
        $response = $this->actingAsUser($this->otherUser)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'name' => 'Updated Trip Name',
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-10',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('trips', [
            'id' => $this->trip->id,
            'name' => 'Updated Trip Name',
            'owner_id' => $this->owner->id,
        ]);
    }

    public function test_unauthorized_user_cannot_update_trip_member(): void
    {
        $response = $this->actingAsUser($this->otherUser)
            ->putJson("/api/v1/trips/{$this->trip->id}/members/{$this->member->id}", [
                'role' => 'editor'
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_view_trip_members(): void
    {
        $response = $this->actingAsUser($this->otherUser)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");

        $response->assertStatus(403);
    }
}
