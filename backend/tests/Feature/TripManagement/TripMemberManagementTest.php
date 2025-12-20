<?php

declare(strict_types=1);

namespace Tests\Feature\TripManagement;

use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\TripTestCase;

/**
 * Tests for trip member management.
 *
 * This class verifies that:
 * - Members can be added to and removed from trips
 * - Role-based permissions are enforced
 * - Member status changes are handled correctly
 * - Editor permissions work as expected
 * - Owner privileges are maintained
 */
#[Group('members')]
#[Group('trip')]
class TripMemberManagementTest extends TripTestCase
{
    protected bool $enableRateLimiting = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trip->members()->attach($this->editor->getKey(), [
            'status' => 'accepted',
            'role' => 'editor'
        ]);
    }

    public function test_owner_can_list_trip_members(): void
    {
        $this->trip->members()->attach($this->member->getKey(), [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($this->owner)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_member_can_view_trip_details(): void
    {
        $this->trip->members()->attach($this->member->getKey(), [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($this->member)
            ->getJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->trip->id);
    }

    public function test_editor_cannot_invite_members_with_invalid_role(): void
    {
        $response = $this->actingAsUser($this->editor)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => $this->otherUser->getAttribute('email'),
                'role' => 'invalid_role'
            ]);
        $response->assertStatus(422);
    }

    public function test_owner_can_delete_trip(): void
    {
        $response = $this->actingAsUser($this->owner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}");
        $response->assertStatus(200);
    }

    public function test_owner_can_remove_member_from_trip(): void
    {
        $this->trip->members()->attach($this->member->getKey(), [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($this->owner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->member->getKey()}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->member->getKey()
        ]);
    }

    public function test_owner_can_remove_editor_from_trip(): void
    {
        $response = $this->actingAsUser($this->owner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->editor->getKey()}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->editor->getKey()
        ]);
    }

    public function test_owner_can_change_member_role(): void
    {
        $this->trip->members()->attach($this->member->getKey(), [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($this->owner)
            ->putJson("/api/v1/trips/{$this->trip->id}/members/{$this->member->getKey()}", [
                'role' => 'editor'
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Role updated.']);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->member->getKey(),
            'role' => 'editor'
        ]);
    }

    public function test_cannot_change_owner_role(): void
    {
        $response = $this->actingAsUser($this->owner)
            ->putJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->getKey()}", [
                'role' => 'editor'
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->owner->getKey(),
            'role' => 'owner'
        ]);
    }

    public function test_cannot_change_to_invalid_role(): void
    {
        $this->trip->members()->attach($this->member->getKey(), [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($this->owner)
            ->putJson("/api/v1/trips/{$this->trip->id}/members/{$this->member->getKey()}", [
                'role' => 'invalid_role'
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->member->getKey(),
            'role' => 'member'
        ]);
    }

    public function test_owner_can_invite_member_to_trip(): void
    {
        $newMember = User::factory()->create();

        $response = $this->actingAsUser($this->owner)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => $newMember->email,
                'role' => 'member'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $newMember->getKey(),
            'status' => 'pending',
            'role' => 'member'
        ]);
    }
    public function test_member_cannot_invite_other_members(): void
    {
        $member = User::factory()->create();
        $this->trip->members()->attach($member->getKey(), ['status' => 'accepted', 'role' => 'member']);

        $newMember = User::factory()->create();

        $response = $this->actingAsUser($member)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => $newMember->email,
                'role' => 'member'
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $newMember->getKey()
        ]);
    }

    public function test_cannot_invite_nonexistent_user(): void
    {
        $response = $this->actingAsUser($this->owner)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => 'nonexistent@example.com',
                'role' => 'member'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
    public function test_user_can_accept_trip_invitation(): void
    {
        $invitedUser = User::factory()->create();
        $this->trip->members()->attach($invitedUser->getKey(), [
            'status' => 'pending',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($invitedUser)
            ->postJson("/api/v1/trips/{$this->trip->id}/accept");

        $response->assertStatus(200);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $invitedUser->getKey(),
            'status' => 'accepted'
        ]);
    }

    public function test_user_can_decline_trip_invitation(): void
    {
        $invitedUser = User::factory()->create();
        $this->trip->members()->attach($invitedUser->getKey(), [
            'status' => 'pending',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($invitedUser)
            ->postJson("/api/v1/trips/{$this->trip->id}/decline");

        $response->assertStatus(200);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $invitedUser->getKey(),
            'status' => 'declined'
        ]);
    }
    public function test_owner_can_manage_members(): void
    {
        $member = User::factory()->create();
        $this->trip->members()->attach($member->getKey(), [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($this->owner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/" . $member->getKey());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $member->getKey()
        ]);
    }

    public function test_member_cannot_manage_other_members(): void
    {
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $this->trip->members()->attach([
            $member1->getKey() => ['status' => 'accepted', 'role' => 'member'],
            $member2->getKey() => ['status' => 'accepted', 'role' => 'member']
        ]);

        $response = $this->actingAsUser($member1)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/" . $member2->getKey());

        $response->assertStatus(403);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $member2->getKey()
        ]);
    }

    public function test_cannot_remove_trip_owner(): void
    {
        $member = $this->createUser();
        $this->trip->members()->attach($member->getKey(), [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAsUser($member)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->getKey()}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->owner->getKey()
        ]);
    }

    public function test_editor_can_update_trip(): void
    {
        $response = $this->actingAsUser($this->editor)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'name' => 'Updated Trip Name',
                'start_date' => now()->addDays()->format('Y-m-d'),
                'end_date' => now()->addDays(5)->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', [
            'id' => $this->trip->id,
            'name' => 'Updated Trip Name'
        ]);
    }

    public function test_editor_can_invite_new_members(): void
    {
        $response = $this->actingAsUser($this->editor)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => $this->otherUser->getAttribute('email'),
                'role' => 'member'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->otherUser->getKey(),
            'status' => 'pending'
        ]);
    }

    public function test_editor_can_remove_members(): void
    {
        $this->trip->members()->attach($this->otherUser->getKey(), [
            'status' => 'accepted',
            'role' => 'member'
        ]);

$response = $this->actingAsUser($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->otherUser->getKey()}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->otherUser->getKey()
        ]);
    }

    public function test_editor_cannot_remove_owner(): void
    {
        $response = $this->actingAsUser($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->getKey()}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->owner->getKey()
        ]);
    }

    public function test_editor_cannot_remove_other_editors(): void
    {
        $anotherEditor = $this->createUser();
        $this->trip->members()->attach($anotherEditor->getKey(), [
            'role' => 'editor',
            'status' => 'accepted'
        ]);

        $response = $this->actingAsUser($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$anotherEditor->getKey()}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $anotherEditor->getKey()
        ]);
    }

    public function test_editor_cannot_delete_trip(): void
    {
        $response = $this->actingAsUser($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trips', ['id' => $this->trip->id]);
    }
}
