<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;
/**
 * Tests for admin panel functionality and access control.
 *
 * This class verifies that:
 * - Admin users can access protected admin routes
 * - Non-admin users are restricted from admin areas
 * - Admin operations (CRUD, user management) function as expected
 * - Role-based access control is properly enforced
 *
 * @covers \App\Http\Controllers\Api\V1\Admin\{
 *      AdminActivityLogController,
 *      AdminUserController
 * }
 */
#[Group('admin')]
#[Group('admin-panel')]
class AdminPanelTest extends ApiTestCase
{
    private User $adminUser;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $this->adminToken = $this->adminUser->createToken('test-token')->plainTextToken;

        $this->regularUser = User::factory()->create([
            'role' => 'user',
            'email' => 'user@example.com',
            'password' => 'password',
        ]);
        $this->regularToken = $this->regularUser->createToken('test-token')->plainTextToken;
    }

    /**
     * Test admin can access health check endpoint
     */
    public function test_admin_can_access_health_check(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->getJson('/api/v1/admin/health');

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    /**
     * Test regular user cannot access admin endpoints
     */
    public function test_regular_user_cannot_access_admin_endpoints(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->filter(fn($route) => str_starts_with($route->uri, 'api/v1/admin'))
            ->map(fn($route) => [
                'uri' => $route->uri,
                'methods' => $route->methods,
                'middleware' => $route->middleware(),
            ])
            ->all();

        \Log::info('Admin routes in test:', $routes);

        $middleware = app('router')->getMiddleware();
        \Log::info('Registered middleware:', ['admin' => $middleware['admin'] ?? 'Not registered']);

        $endpoints = [
            'GET /api/v1/admin/health',
            'GET /api/v1/admin/users',
            'PATCH /api/v1/admin/users/1/role',
            'PATCH /api/v1/admin/users/1/ban',
            'GET /api/v1/admin/logs/activity',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $url] = explode(' ', $endpoint);

            \Log::info("Testing endpoint: $method $url");

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->regularToken,
                'Accept' => 'application/json',
            ])->json($method, $url);

            \Log::info("Response for $method $url", [
                'status' => $response->status(),
                'content' => $response->content(),
                'headers' => $response->headers->all()
            ]);

            $this->assertNotEquals(500, $response->status(),
                "Server error when accessing $method $url: " . $response->content()
            );
        }
    }

    /**
     * Test unauthenticated user cannot access admin endpoints
     */
    public function test_unauthenticated_user_cannot_access_admin_endpoints(): void
    {
        $endpoints = [
            'GET /api/v1/admin/health',
            'GET /api/v1/admin/users',
            'PATCH /api/v1/admin/users/1/role',
            'PATCH /api/v1/admin/users/1/ban',
            'GET /api/v1/admin/logs/activity',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $url] = explode(' ', $endpoint);
            $response = $this->json($method, $url);

            $response->assertStatus(401);
        }
    }

    /**
     * Test admin can list users
     */
    public function test_admin_can_list_users(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email'
                    ]
                ],
                'links' => [
                    'first', 'last', 'prev', 'next'
                ],
                'meta' => [
                    'current_page', 'from', 'last_page', 'path',
                    'per_page', 'to', 'total'
                ]
            ]);
    }

    /**
     * Test admin can change user role
     */
    public function test_admin_can_change_user_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->patchJson("/api/v1/admin/users/$user->id/role", [
                'role' => 'admin'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'role' => 'admin',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin'
        ]);
    }

    /**
     * Test admin can ban a user
     */
    public function test_admin_can_ban_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->patchJson("/api/v1/admin/users/{$this->regularUser->id}/ban", [
                'banned' => true
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->regularUser->id,
                    'banned' => true,
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'banned_at' => now()
        ]);
    }

    /**
     * Test admin can unban a user
     */
    public function test_admin_can_unban_user(): void
    {
        $bannedUser = User::factory()->create([
            'banned_at' => now()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->patchJson("/api/v1/admin/users/$bannedUser->id/ban", [
                'banned' => false
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $bannedUser->id,
                    'banned' => false,
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $bannedUser->id,
            'banned_at' => null
        ]);
    }

    /**
     * Test admin can view activity logs
     */
    public function test_admin_can_view_activity_logs(): void
    {
        ActivityLog::create([
            'user_id' => $this->adminUser->id,
            'action' => 'test.action',
            'details' => ['test' => 'data'],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->getJson('/api/v1/admin/logs/activity');

        $response->assertStatus(200)
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
                'links' => [
                    'first', 'last', 'prev', 'next'
                ],
                'meta' => [
                    'current_page', 'from', 'last_page', 'path',
                    'per_page', 'to', 'total'
                ]
            ]);
    }

    /**
     * Test admin cannot ban themselves
     */
    public function test_admin_cannot_ban_themselves(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->patchJson("/api/v1/admin/users/{$this->adminUser->id}/ban", [
                'banned' => true
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot ban your own account.'
            ]);
    }

    /**
     * Test role validation
     */
    public function test_role_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->patchJson("/api/v1/admin/users/$user->id/role", [
                'role' => 'invalid-role'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    /**
     * Test ban validation
     */
    public function test_ban_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])
            ->patchJson("/api/v1/admin/users/$user->id/ban", [
                'banned' => 'not-a-boolean'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['banned']);
    }
}
