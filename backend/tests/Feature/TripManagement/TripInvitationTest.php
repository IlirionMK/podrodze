<?php

declare(strict_types=1);

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\TripTestCase;

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
class TripInvitationTest extends TripTestCase
{
    protected bool $enableRateLimiting = false;

    protected User $invitedUser;
    protected User $otherUser;
    protected User $owner;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invitedUser = User::factory()->create(['email' => 'kumpel@example.com']);
        $this->otherUser = User::factory()->create();
    }

    public function test_owner_can_invite_user_by_email(): void
    {

        $response = $this->actingAsUser($this->owner)
            ->postJson("/api/v1/trips/{$this->trip->getKey()}/members/invite", [
                'email' => 'kumpel@example.com',
                'role' => 'editor'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->getKey(),
            'user_id' => $this->invitedUser->getKey(),
            'status' => 'pending',
        ]);
    }

    public function test_invited_user_can_accept_invitation(): void
    {
        $members = $this->trip->members();
        $members->attach($this->invitedUser->getKey(), [
            'status' => 'pending',
            'role' => 'viewer'
        ]);

        $response = $this->actingAsUser($this->invitedUser)
            ->postJson("/api/v1/trips/{$this->trip->id}/accept");

        $response->assertSuccessful();

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->getKey(),
            'user_id' => $this->invitedUser->getKey(),
            'status' => 'accepted',
        ]);
    }

    public function test_invited_user_can_decline_invitation(): void
    {
        $this->trip->members()->attach($this->invitedUser->getKey(), [
            'status' => 'pending',
            'role' => 'viewer'
        ]);

        $response = $this->actingAsUser($this->invitedUser)
            ->postJson("/api/v1/trips/{$this->trip->getKey()}/decline");

        $response->assertSuccessful();

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->getKey(),
            'user_id' => $this->invitedUser->getKey(),
            'status' => 'declined',
        ]);
    }

    public function test_cannot_accept_previously_declined_invitation(): void
    {
        $this->trip->members()->attach($this->invitedUser->getKey(), [
            'status' => 'declined',
            'role' => 'viewer'
        ]);

        $response = $this->actingAsUser($this->invitedUser)
            ->postJson("/api/v1/trips/{$this->trip->id}/accept");

        $response->assertStatus(403);
        $this->assertEquals('This action is unauthorized.', $response->json('message'));
    }

    public function test_user_can_list_pending_invitations(): void
    {
        $trip2 = Trip::factory()->create(['owner_id' => $this->otherUser->getKey()]);

        $this->trip->members()->attach($this->invitedUser->getKey(), [
            'status' => 'pending',
            'role' => 'viewer'
        ]);

        $trip2->members()->attach($this->invitedUser->getKey(), [
            'status' => 'accepted',
            'role' => 'editor'
        ]);

        $response = $this->actingAsUser($this->invitedUser)
            ->getJson('/api/v1/users/me/invites');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trip_id', $this->trip->getKey());
    }

    public function test_owner_can_list_sent_invitations(): void
    {
        $this->trip->members()->attach($this->invitedUser->getKey(), [
            'status' => 'pending',
            'role' => 'viewer'
        ]);
        $this->trip->members()->attach($this->otherUser->getKey(), [
            'status' => 'pending',
            'role' => 'editor'
        ]);

        $response = $this->actingAsUser($this->owner)
            ->getJson('/api/v1/users/me/invites/sent');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_invite_already_accepted_member(): void
    {
        $this->trip->members()->attach($this->invitedUser->getKey(), [
            'status' => 'accepted',
            'role' => 'viewer'
        ]);

        $response = $this->actingAsUser($this->owner)
            ->postJson("/api/v1/trips/{$this->trip->getKey()}/members/invite", [
                'email' => $this->invitedUser->email,
                'role' => 'editor'
            ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('already a member', $response->json('error'));
    }

    public function test_uninvited_user_cannot_join_trip(): void
    {
        $response = $this->actingAsUser($this->otherUser)
            ->postJson("/api/v1/trips/{$this->trip->getKey()}/accept");

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]));

        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->getKey(),
            'user_id' => $this->owner->getKey(),
            'status' => 'joined',
        ]);
    }
}
