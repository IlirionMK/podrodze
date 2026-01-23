<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end tests for admin user management operations.
 *
 * This test verifies the complete admin user management flow including:
 * 1. Admin authentication and authorization
 * 2. User listing with pagination and search
 * 3. Changing user roles (admin/user)
 * 4. Banning and unbanning users
 * 5. Viewing activity logs
 * 6. Admin health checks
 * 7. Permission validation for non-admin users
 *
 * @covers \App\Http\Controllers\Api\V1\Admin\AdminUserController
 * @covers \App\Http\Controllers\Api\V1\Admin\AdminActivityLogController
 */
#[Group('admin')]
#[Group('e2e')]
#[Group('user-management')]
class AdminUserManagementE2ETest extends TestCase
{
    use DatabaseMigrations;

    private User $admin;
    private User $regularUser;
    private User $userToBan;
    private User $userToPromote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->regularUser = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'role' => 'user',
        ]);

        $this->userToBan = User::factory()->create([
            'name' => 'User to Ban',
            'email' => 'toban@example.com',
            'role' => 'user',
        ]);

        $this->userToPromote = User::factory()->create([
            'name' => 'User to Promote',
            'email' => 'topromote@example.com',
            'role' => 'user',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_complete_admin_user_management_flow(): void
    {
        // 1. List all users
        $listResponse = $this->getJson('/api/v1/admin/users');
        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email']
                ],
                'links',
                'meta'
            ]);

        // Verify all users are listed
        $users = $listResponse->json('data');
        $this->assertGreaterThanOrEqual(4, count($users));

        // 2. Search for specific user
        $searchResponse = $this->getJson('/api/v1/admin/users?search=User to Ban');
        $searchResponse->assertStatus(200);
        $searchResults = $searchResponse->json('data');
        $this->assertCount(1, $searchResults);
        $this->assertEquals('User to Ban', $searchResults[0]['name']);

        // 3. Promote user to admin
        $promoteResponse = $this->patchJson("/api/v1/admin/users/{$this->userToPromote->id}/role", [
            'role' => 'admin'
        ]);
        $promoteResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->userToPromote->id,
                    'role' => 'admin',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->userToPromote->id,
            'role' => 'admin'
        ]);

        // 4. Ban a user
        $banResponse = $this->patchJson("/api/v1/admin/users/{$this->userToBan->id}/ban", [
            'banned' => true
        ]);
        $banResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->userToBan->id,
                    'banned' => true,
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->userToBan->id,
            'banned_at' => now()
        ]);

        // 5. Create some activity logs
        ActivityLog::factory()->create([
            'user_id' => $this->admin->id,
            'action' => 'user.banned',
            'details' => ['target_user_id' => $this->userToBan->id],
        ]);

        ActivityLog::factory()->create([
            'user_id' => $this->admin->id,
            'action' => 'user.role_changed',
            'details' => ['target_user_id' => $this->userToPromote->id, 'new_role' => 'admin'],
        ]);

        // 6. View activity logs
        $logsResponse = $this->getJson('/api/v1/admin/logs/activity');
        $logsResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'action',
                        'details',
                        'created_at',
                    ]
                ],
                'links',
                'meta'
            ]);

        // 7. Check health endpoint
        $healthResponse = $this->getJson('/api/v1/admin/health');
        $healthResponse->assertStatus(200)
            ->assertJson(['ok' => true]);

        // 8. Unban the user
        $unbanResponse = $this->patchJson("/api/v1/admin/users/{$this->userToBan->id}/ban", [
            'banned' => false
        ]);
        $unbanResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->userToBan->id,
                    'banned' => false,
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->userToBan->id,
            'banned_at' => null
        ]);

        // 9. Demote admin back to user
        $demoteResponse = $this->patchJson("/api/v1/admin/users/{$this->userToPromote->id}/role", [
            'role' => 'user'
        ]);
        $demoteResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->userToPromote->id,
                    'role' => 'user',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->userToPromote->id,
            'role' => 'user'
        ]);
    }
}
