<?php

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

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
class TripAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $editor;
    protected User $member;
    protected User $otherUser;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->editor = User::factory()->create();
        $this->member = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->trip = Trip::factory()->create(['owner_id' => $this->owner->id]);

        $this->trip->members()->attach($this->editor->id, [
            'status' => 'accepted',
            'role' => 'editor'
        ]);

        $this->trip->members()->attach($this->member->id, [
            'status' => 'accepted',
            'role' => 'member'
        ]);
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
            ['post', "/api/v1/trips/{$this->trip->id}/members/invite"],
            ['delete', "/api/v1/trips/{$this->trip->id}/members/1"],
        ];

        foreach ($routes as $route) {
            [$method, $uri] = $route;
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    public function test_member_access(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson("/api/v1/trips/{$this->trip->id}");
        $response->assertStatus(200);

        $response = $this->actingAs($this->member)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");
        $response->assertStatus(200);
    }

    public function test_owner_cannot_be_removed(): void
    {
        $response = $this->actingAs($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->id}");
        $response->assertStatus(403);

        $anotherOwner = User::factory()->create();
        $response = $this->actingAs($anotherOwner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->id}");
        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_update_trip_member(): void
    {
        $response = $this->actingAs($this->otherUser)
            ->putJson("/api/v1/trips/{$this->trip->id}/members/{$this->member->id}", [
                'role' => 'editor'
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_view_trip_members(): void
    {
        $response = $this->actingAs($this->otherUser)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");

        $response->assertStatus(403);
    }
}
