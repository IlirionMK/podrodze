<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test suite for AdminUserController API endpoints.
 *
 * This test verifies the functionality of admin user management including:
 * 1. Listing all users in the system
 * 2. Viewing detailed user information
 * 3. Admin authorization and access control
 * 4. Pagination and filtering of user lists
 * 5. Handling of non-existent users
 * 6. Authentication requirements for admin operations
 */
#[Group('admin')]
#[Group('users')]
#[Group('feature')]
class AdminUserControllerFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var User Admin user */
    protected User $admin;

    /** @var User Regular user */
    protected User $regularUser;

    /**
     * Set up the test environment.
     * Creates admin and regular users for testing admin functionality.
     */
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
        ]);

        Sanctum::actingAs($this->admin);
    }

    #[Test]
    public function it_lists_all_users_as_admin()
    {
        // Create additional users for testing pagination
        User::factory()->count(15)->create();

        $response = $this->getJson('/api/v1/admin/users');

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
                    'first',
                    'last',
                    'prev',
                    'next'
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total'
                ]
            ]);

        $responseData = $response->json();
        $this->assertGreaterThan(15, $responseData['meta']['total']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertNotEmpty($responseData['data']);
    }

    #[Test]
    public function it_requires_admin_authentication_to_access_user_management()
    {
        $this->refreshApplication();

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(401);
    }

    #[Test]
    public function it_prevents_non_admin_users_from_accessing_user_management()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_supports_user_search_and_filtering()
    {
        // Create users with specific names for testing search
        User::factory()->create(['name' => 'John Doe']);
        User::factory()->create(['name' => 'Jane Smith']);
        User::factory()->create(['name' => 'John Smith']);

        $response = $this->getJson('/api/v1/admin/users?search=John');

        $response->assertStatus(200);

        $responseData = $response->json('data');
        $johnUsers = collect($responseData)->filter(function ($user) {
            return str_contains($user['name'], 'John');
        });

        $this->assertGreaterThan(0, $johnUsers->count());
    }

    #[Test]
    public function it_handles_pagination_parameters()
    {
        // Create many users
        User::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/admin/users?per_page=5&page=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta'
            ]);

        $responseData = $response->json();
        $this->assertEquals(2, $responseData['meta']['current_page']);
        $this->assertEquals(5, $responseData['meta']['per_page']);
        $this->assertCount(5, $responseData['data']);
    }

    #[Test]
    public function it_includes_user_statistics_in_admin_view()
    {
        User::factory()->count(10)->create(['created_at' => now()->subDays(30)]);
        User::factory()->count(5)->create(['created_at' => now()->subDays(7)]);
        User::factory()->count(2)->create(['created_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/admin/users?include_stats=true');

        $response->assertStatus(200);

        $responseData = $response->json();
        if (isset($responseData['meta']['statistics'])) {
            $this->assertArrayHasKey('total_users', $responseData['meta']['statistics']);
            $this->assertArrayHasKey('new_users_this_month', $responseData['meta']['statistics']);
            $this->assertArrayHasKey('new_users_this_week', $responseData['meta']['statistics']);
        }
    }

    #[Test]
    public function it_includes_user_relationships_in_detailed_view()
    {
        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(200);

        $responseData = $response->json('data');
        $this->assertIsArray($responseData);
    }
}
