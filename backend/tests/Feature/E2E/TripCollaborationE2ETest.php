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

    public function test_create_trip_with_details(): void
    {
        Sanctum::actingAs($this->owner);

        $tripData = [
            'name' => 'Summer Vacation 2024',
            'description' => 'A trip to the mountains with friends',
            'start_date' => '2024-07-15',
            'end_date' => '2024-07-25',
        ];

        $response = $this->postJson('/api/v1/trips', $tripData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'start_date',
                    'end_date',
                    'owner_id',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Summer Vacation 2024',
                    'description' => 'A trip to the mountains with friends',
                    'owner_id' => $this->owner->id,
                ]
            ]);

        $this->assertDatabaseHas('trips', [
            'name' => 'Summer Vacation 2024',
            'owner_id' => $this->owner->id,
        ]);
    }

    public function test_invite_multiple_users_to_trip(): void
    {
        Sanctum::actingAs($this->owner);
        $trip = Trip::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Group Hiking Trip'
        ]);

        $trip->members()->attach($this->member1->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/members/invite", [
            'email' => $this->member2->email,
            'role' => 'member'
        ]);

        $response->assertStatus(200);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/members/invite", [
            'email' => $this->invitee->email,
            'role' => 'member'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Invitation sent successfully.',
            ]);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $this->member2->id,
            'status' => 'pending',
            'role' => 'member'
        ]);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $this->invitee->id,
            'status' => 'pending',
            'role' => 'member'
        ]);
    }

    public function test_accept_decline_trip_invitation(): void
    {
        Sanctum::actingAs($this->owner);
        $trip = Trip::factory()->create(['owner_id' => $this->owner->id]);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/members/invite", [
            'email' => $this->invitee->email,
            'role' => 'member'
        ]);
        $response->assertStatus(200);

        Sanctum::actingAs($this->invitee);

        $response = $this->getJson('/api/v1/users/me/invites');
        $response->assertStatus(200);
        $invitation = $response->json('data.0');

        $response = $this->postJson("/api/v1/trips/{$invitation['trip_id']}/accept");
        $response->assertStatus(200);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $this->invitee->id,
            'status' => 'accepted',
            'role' => 'member'
        ]);

        Sanctum::actingAs($this->owner);
        $response = $this->postJson("/api/v1/trips/{$trip->id}/members/invite", [
            'email' => $this->member2->email,
            'role' => 'member'
        ]);
        $response->assertStatus(200);

        Sanctum::actingAs($this->member2);

        $response = $this->getJson('/api/v1/users/me/invites');
        $response->assertStatus(200);
        $invitation = $response->json('data.0');

        $response = $this->postJson("/api/v1/trips/{$invitation['trip_id']}/decline");
        $response->assertStatus(200);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $this->member2->id,
            'status' => 'declined',
            'role' => 'member'
        ]);
    }

    public function test_role_based_access_control(): void
    {
        $trip = Trip::factory()->create(['owner_id' => $this->owner->id]);
        $trip->members()->attach($this->member1->id, ['role' => 'member', 'status' => 'accepted']);
        $trip->members()->attach($this->member2->id, ['role' => 'editor', 'status' => 'accepted']);

        Sanctum::actingAs($this->owner);
        $response = $this->putJson("/api/v1/trips/{$trip->id}", [
            'title' => 'Updated Trip Title',
            'description' => 'Updated description',
        ]);
        $response->assertStatus(200);

        Sanctum::actingAs($this->member2);
        $response = $this->putJson("/api/v1/trips/{$trip->id}", [
            'description' => 'Updated by editor',
        ]);
        $response->assertStatus(200);

        Sanctum::actingAs($this->member1);
        $response = $this->putJson("/api/v1/trips/{$trip->id}", [
            'description' => 'This should fail',
        ]);
        $response->assertStatus(403);

        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);
        $response = $this->getJson("/api/v1/trips/{$trip->id}");
        $response->assertStatus(403);
    }

    public function test_real_time_updates_for_collaboration()
    {
        $trip = Trip::factory()->create(['owner_id' => $this->owner->id]);
        $trip->members()->attach($this->member1->id, ['role' => 'editor', 'status' => 'accepted']);
        $trip->members()->attach($this->member2->id, ['role' => 'member', 'status' => 'accepted']);

        Sanctum::actingAs($this->member1);
        $response = $this->putJson("/api/v1/trips/{$trip->id}", [
            'description' => 'Updated by member1',
        ]);
        $response->assertStatus(200);

        Sanctum::actingAs($this->member2);
        $response = $this->getJson("/api/v1/trips/{$trip->id}");
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'description' => 'Updated by member1',
                ]
            ]);

    }
}
