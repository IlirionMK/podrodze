<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Tests for trip invitation system.
 *
 * This class verifies that:
 * - Trip owners can invite users via email
 * - Invitations can be accepted or declined
 * - Invitation status is properly tracked
 * - Only valid invitations can be accepted
 * - Invitation listing works as expected
 */
#[Group('invitation')]
#[Group('trip')]
class TripInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_user_by_email(): void
    {
        $owner = User::factory()->create();
        $userToInvite = User::factory()->create(['email' => 'kumpel@example.com']);
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/trips/{$trip->id}/members/invite", [
                'email' => 'kumpel@example.com',
                'role' => 'editor'
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $userToInvite->id,
            'status' => 'pending',
        ]);
    }

    public function test_invited_user_can_accept_invitation(): void
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $trip->members()->attach($invitedUser->id, ['status' => 'pending', 'role' => 'viewer']);

        $response = $this->actingAs($invitedUser)
            ->postJson("/api/v1/trips/{$trip->id}/accept");

        $response->assertSuccessful();

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $invitedUser->id,
            'status' => 'accepted',
        ]);
    }

    public function test_invited_user_can_decline_invitation(): void
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $trip->members()->attach($invitedUser->id, ['status' => 'pending', 'role' => 'viewer']);

        $response = $this->actingAs($invitedUser)
            ->postJson("/api/v1/trips/{$trip->id}/decline");

        $response->assertSuccessful();

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $invitedUser->id,
            'status' => 'declined',
        ]);
    }

    public function test_cannot_accept_previously_declined_invitation(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $trip->members()->attach($user->id, ['status' => 'declined', 'role' => 'viewer']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/trips/{$trip->id}/accept");

        $response->assertStatus(403);
        $this->assertEquals('This action is unauthorized.', $response->json('message'));
    }

    public function test_user_can_list_pending_invitations(): void
    {
        $owner1 = User::factory()->create();
        $owner2 = User::factory()->create();
        $user = User::factory()->create();

        $trip1 = Trip::factory()->create(['owner_id' => $owner1->id]);
        $trip2 = Trip::factory()->create(['owner_id' => $owner2->id]);

        $trip1->members()->attach($user->id, ['status' => 'pending', 'role' => 'viewer']);

        $trip2->members()->attach($user->id, ['status' => 'accepted', 'role' => 'editor']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users/me/invites');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trip_id', $trip1->id);
    }

    public function test_owner_can_list_sent_invitations(): void
    {
        $owner = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $trip->members()->attach($user1->id, ['status' => 'pending', 'role' => 'viewer']);
        $trip->members()->attach($user2->id, ['status' => 'pending', 'role' => 'editor']);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/users/me/invites/sent');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_invite_already_accepted_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $trip->members()->attach($member->id, ['status' => 'accepted', 'role' => 'viewer']);

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/trips/{$trip->id}/members/invite", [
                'email' => $member->email,
                'role' => 'editor'
            ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('already a member', $response->json('error'));
    }

    public function test_uninvited_user_cannot_join_trip(): void
    {
        $owner = User::factory()->create();
        $randomUser = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($randomUser)
            ->postJson("/api/v1/trips/{$trip->id}/accept");

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]));

        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $randomUser->id,
            'status' => 'joined',
        ]);
    }
}
