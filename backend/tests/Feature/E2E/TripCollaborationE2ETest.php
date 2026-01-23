<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end test for trip collaboration features.
 *
 * This test verifies the complete collaboration flow for trips, including:
 * 1. Trip creation and basic CRUD operations
 * 2. Inviting members to trips
 * 3. Managing trip members (adding/removing)
 * 4. Member permissions and access control
 * 5. Real-time collaboration features
 *
 * @covers \App\Http\Controllers\Trip\TripController
 * @covers \App\Http\Controllers\Trip\TripMemberController
 * @covers \App\Http\Controllers\Trip\TripInvitationController
 * @covers \App\Models\Trip
 * @covers \App\Models\TripMember
 * @covers \App\Models\Invitation
 */
#[Group('trip')]
#[Group('collaboration')]
#[Group('e2e')]
class TripCollaborationE2ETest extends TestCase
{
    use DatabaseMigrations;

    /** @var User Trip owner user instance */
    private User $owner;

    /** @var User First trip member user instance */
    private User $member1;

    /** @var User Second trip member user instance */
    private User $member2;

    /** @var User User invited to the trip */
    private User $invitee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'name' => 'Trip Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->member1 = User::factory()->create([
            'name' => 'Member One',
            'email' => 'member1@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->member2 = User::factory()->create([
            'name' => 'Member Two',
            'email' => 'member2@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->invitee = User::factory()->create([
            'name' => 'Invited User',
            'email' => 'invitee@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_complete_trip_collaboration_flow(): void
    {
        // Step 1: Owner creates a trip
        Sanctum::actingAs($this->owner);
        
        $tripData = [
            'name' => 'European Adventure 2024',
            'description' => 'A trip through Europe with friends',
            'start_date' => '2024-06-15',
            'end_date' => '2024-06-30',
        ];

        $createResponse = $this->postJson('/api/v1/trips', $tripData);
        $tripId = $createResponse->json('data.id');
        
        $createResponse->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'European Adventure 2024',
                    'owner_id' => $this->owner->id,
                ]
            ]);

        // Step 2: Owner invites first member (editor role)
        $inviteResponse = $this->postJson("/api/v1/trips/{$tripId}/members/invite", [
            'email' => $this->member1->email,
            'role' => 'editor'
        ]);
        $inviteResponse->assertStatus(200);

        // Step 3: Owner invites second member (member role)
        $inviteResponse = $this->postJson("/api/v1/trips/{$tripId}/members/invite", [
            'email' => $this->member2->email,
            'role' => 'member'
        ]);
        $inviteResponse->assertStatus(200);

        // Step 4: First member accepts invitation
        Sanctum::actingAs($this->member1);
        $invitesResponse = $this->getJson('/api/v1/users/me/invites');
        $invitation = $invitesResponse->json('data.0');
        
        $acceptResponse = $this->postJson("/api/v1/trips/{$invitation['trip_id']}/accept");
        $acceptResponse->assertStatus(200);

        // Step 5: Second member accepts invitation
        Sanctum::actingAs($this->member2);
        $invitesResponse = $this->getJson('/api/v1/users/me/invites');
        $invitation = $invitesResponse->json('data.0');
        
        $acceptResponse = $this->postJson("/api/v1/trips/{$invitation['trip_id']}/accept");
        $acceptResponse->assertStatus(200);

        // Step 6: Verify all members are now part of the trip
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $tripId,
            'user_id' => $this->member1->id,
            'status' => 'accepted',
            'role' => 'editor'
        ]);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $tripId,
            'user_id' => $this->member2->id,
            'status' => 'accepted',
            'role' => 'member'
        ]);

        // Step 7: Test role-based permissions - owner can edit
        Sanctum::actingAs($this->owner);
        $updateResponse = $this->putJson("/api/v1/trips/{$tripId}", [
            'description' => 'Updated by owner'
        ]);
        $updateResponse->assertStatus(200);

        // Step 8: Test role-based permissions - editor can edit
        Sanctum::actingAs($this->member1);
        $updateResponse = $this->putJson("/api/v1/trips/{$tripId}", [
            'description' => 'Updated by editor'
        ]);
        $updateResponse->assertStatus(200);

        // Step 9: Test role-based permissions - member cannot edit
        Sanctum::actingAs($this->member2);
        $updateResponse = $this->putJson("/api/v1/trips/{$tripId}", [
            'description' => 'This should fail'
        ]);
        $updateResponse->assertStatus(403);

        // Step 10: Test real-time collaboration - member can view updates
        $viewResponse = $this->getJson("/api/v1/trips/{$tripId}");
        $viewResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'description' => 'Updated by editor'
                ]
            ]);

        // Step 11: Owner invites new user who declines
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/trips/{$tripId}/members/invite", [
            'email' => $this->invitee->email,
            'role' => 'member'
        ]);

        Sanctum::actingAs($this->invitee);
        $invitesResponse = $this->getJson('/api/v1/users/me/invites');
        $invitation = $invitesResponse->json('data.0');
        
        $declineResponse = $this->postJson("/api/v1/trips/{$invitation['trip_id']}/decline");
        $declineResponse->assertStatus(200);

        // Step 12: Verify declined invitation
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $tripId,
            'user_id' => $this->invitee->id,
            'status' => 'declined',
            'role' => 'member'
        ]);

        // Step 13: Non-member cannot access trip
        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);
        $accessResponse = $this->getJson("/api/v1/trips/{$tripId}");
        $accessResponse->assertStatus(403);
    }
}
