<?php

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test suite for TripUserController API endpoints.
 *
 * This test verifies the functionality of trip member management including:
 * 1. Listing trip members
 * 2. Inviting users to trips
 * 3. Managing member roles
 * 4. Removing members from trips
 * 5. Accepting/declining trip invitations
 * 6. Viewing user invitations (received and sent)
 * 7. Authorization and access control for member operations
 */
#[Group('trip')]
#[Group('members')]
#[Group('collaboration')]
#[Group('feature')]
class TripUserControllerFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var User Test trip owner */
    protected User $owner;

    /** @var User Test member user */
    protected User $member;

    /** @var User Test non-member user */
    protected User $nonMember;

    /** @var Trip Test trip instance */
    protected Trip $trip;

    /**
     * Set up the test environment.
     * Creates test users and trip for testing member management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'name' => 'Trip Owner',
            'email' => 'owner@example.com',
        ]);

        $this->member = User::factory()->create([
            'name' => 'Trip Member',
            'email' => 'member@example.com',
        ]);

        $this->nonMember = User::factory()->create([
            'name' => 'Non Member',
            'email' => 'nonmember@example.com',
        ]);

        $this->trip = Trip::factory()->create([
            'name' => 'Test Trip',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'owner_id' => $this->owner->id,
        ]);

        // Add member to trip
        $this->trip->members()->attach($this->member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        Sanctum::actingAs($this->owner);
    }

    #[Test]
    public function it_lists_trip_members()
    {
        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/members");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'role'
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertCount(2, $responseData); // Owner + 1 member

        // Verify owner is included with correct role
        $ownerData = collect($responseData)->firstWhere('id', $this->owner->id);
        $this->assertNotNull($ownerData);
        $this->assertEquals('owner', $ownerData['role']);

        // Verify member is included with correct role
        $memberData = collect($responseData)->firstWhere('id', $this->member->id);
        $this->assertNotNull($memberData);
        $this->assertEquals('member', $memberData['role']);
    }

    #[Test]
    public function it_requires_authentication_to_list_members()
    {
        $this->refreshApplication();

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/members");

        $response->assertStatus(401);
    }

    #[Test]
    public function it_prevents_non_members_from_listing_members()
    {
        $response = $this->actingAs($this->nonMember)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_members_to_list_members()
    {
        $response = $this->actingAs($this->member)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_invites_user_to_trip()
    {
        $inviteData = [
            'email' => $this->nonMember->email,
            'role' => 'member',
            'message' => 'Join our trip!'
        ];

        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/members/invite", $inviteData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'trip_id',
                    'name',
                    'status',
                    'role'
                ]
            ]);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->nonMember->id,
            'role' => 'member',
            'status' => 'pending'
        ]);
    }

    #[Test]
    public function it_validates_invitation_data()
    {
        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
            'email' => 'invalid-email',
            'role' => 'invalid-role'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'role']);
    }

    #[Test]
    public function it_prevents_non_owners_from_inviting()
    {
        $response = $this->actingAs($this->member)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => 'newuser@example.com',
                'role' => 'member'
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_prevents_inviting_existing_members()
    {
        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
            'email' => $this->member->email,
            'role' => 'member'
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'This user is already a member of the trip.']);
    }

    #[Test]
    public function it_updates_member_role()
    {
        $response = $this->putJson("/api/v1/trips/{$this->trip->id}/members/{$this->member->id}", [
            'role' => 'editor'
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Role updated.']);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->member->id,
            'role' => 'editor'
        ]);
    }

    #[Test]
    public function it_prevents_updating_owner_role()
    {
        $response = $this->putJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->id}", [
            'role' => 'member'
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_prevents_non_owners_from_updating_roles()
    {
        $newUser = User::factory()->create();
        $this->trip->members()->attach($newUser->id, ['role' => 'member', 'status' => 'accepted']);

        $response = $this->actingAs($this->member)
            ->putJson("/api/v1/trips/{$this->trip->id}/members/{$newUser->id}", [
                'role' => 'editor'
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_removes_member_from_trip()
    {
        $response = $this->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->member->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Member removed.']);

        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->member->id
        ]);
    }

    #[Test]
    public function it_prevents_removing_owner()
    {
        $response = $this->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_prevents_non_owners_from_removing_members()
    {
        $newUser = User::factory()->create();
        $this->trip->members()->attach($newUser->id, ['role' => 'member', 'status' => 'accepted']);

        $response = $this->actingAs($this->member)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$newUser->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_accepts_trip_invitation()
    {
        // Create an invitation by adding user with pending status
        $this->trip->members()->attach($this->nonMember->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->nonMember)
            ->postJson("/api/v1/trips/{$this->trip->id}/accept");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Invitation accepted.']);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->nonMember->id,
            'role' => 'member',
            'status' => 'accepted'
        ]);
    }

    #[Test]
    public function it_declines_trip_invitation()
    {
        // Create an invitation by adding user with pending status
        $this->trip->members()->attach($this->nonMember->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->nonMember)
            ->postJson("/api/v1/trips/{$this->trip->id}/decline");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Invitation declined.']);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->nonMember->id,
            'role' => 'member',
            'status' => 'declined'
        ]);
    }

    #[Test]
    public function it_prevents_accepting_nonexistent_invitation()
    {
        $response = $this->actingAs($this->nonMember)
            ->postJson("/api/v1/trips/{$this->trip->id}/accept");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_prevents_accepting_already_processed_invitation()
    {
        // Create and accept invitation
        $this->trip->members()->attach($this->nonMember->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $response = $this->actingAs($this->nonMember)
            ->postJson("/api/v1/trips/{$this->trip->id}/accept");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_lists_user_received_invites()
    {
        // Create multiple invitations
        $trip2 = Trip::factory()->create(['owner_id' => $this->owner->id]);
        
        $this->trip->members()->attach($this->nonMember->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $trip2->members()->attach($this->nonMember->id, [
            'role' => 'editor',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->nonMember)
            ->getJson('/api/v1/users/me/invites');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'trip_id',
                        'name',
                        'status',
                        'role'
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertCount(2, $responseData);
    }

    #[Test]
    public function it_lists_user_sent_invites()
    {
        // Create multiple invitations sent by owner
        $trip2 = Trip::factory()->create(['owner_id' => $this->owner->id]);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);
        
        $this->trip->members()->attach($user2->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $trip2->members()->attach($this->nonMember->id, [
            'role' => 'editor',
            'status' => 'pending'
        ]);

        $response = $this->getJson('/api/v1/users/me/invites/sent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'trip_id',
                        'name',
                        'status',
                        'role'
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertCount(2, $responseData);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_trip_in_member_operations()
    {
        $nonExistentId = 99999;

        $response = $this->getJson("/api/v1/trips/{$nonExistentId}/members");
        $response->assertStatus(404);

        $response = $this->postJson("/api/v1/trips/{$nonExistentId}/members/invite", [
            'email' => 'test@example.com',
            'role' => 'member'
        ]);
        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_user_in_member_operations()
    {
        $nonExistentUserId = 99999;

        $response = $this->putJson("/api/v1/trips/{$this->trip->id}/members/{$nonExistentUserId}", [
            'role' => 'editor'
        ]);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$nonExistentUserId}");
        $response->assertStatus(404);
    }

    #[Test]
    public function it_handles_member_management_with_editor_role()
    {
        // Add editor to trip
        $editor = User::factory()->create();
        $this->trip->members()->attach($editor->id, ['role' => 'editor', 'status' => 'accepted']);

        // Editor should be able to view members but not manage them
        $response = $this->actingAs($editor)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");
        $response->assertStatus(200);

        $response = $this->actingAs($editor)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => 'newuser@example.com',
                'role' => 'member'
            ]);
        $response->assertStatus(422);
    }
}
