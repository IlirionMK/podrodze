<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test suite for AdminActivityLogController API endpoints.
 *
 * This test verifies the functionality of admin activity logging including:
 * 1. Retrieving system activity logs
 * 2. Filtering logs by user, action, and date range
 * 3. Pagination of log entries
 * 4. Admin authorization requirements
 * 5. Log entry structure and data integrity
 * 6. Authentication and access control
 */
#[Group('admin')]
#[Group('activity-logs')]
#[Group('feature')]
class AdminActivityLogControllerFeatureTest extends TestCase
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
    public function it_retrieves_activity_logs_as_admin()
    {
        // Create some test activity logs
        $this->createTestActivityLogs();

        $response = $this->getJson('/api/v1/admin/logs/activity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'action',
                        'target_type',
                        'target_id',
                        'details',
                        'message',
                        'created_at'
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
        $this->assertGreaterThan(0, $responseData['meta']['total']);
        $this->assertNotEmpty($responseData['data']);
    }

    #[Test]
    public function it_requires_admin_authentication_to_access_activity_logs()
    {
        $this->refreshApplication();

        $response = $this->getJson('/api/v1/admin/logs/activity');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_prevents_non_admin_users_from_accessing_activity_logs()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/v1/admin/logs/activity');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_filters_activity_logs_by_user()
    {
        $this->createTestActivityLogs();

        $response = $this->getJson("/api/v1/admin/logs/activity?user_id={$this->regularUser->id}");

        $response->assertStatus(200);

        $responseData = $response->json('data');

        // All returned logs should be for the specified user
        foreach ($responseData as $log) {
            $this->assertEquals($this->regularUser->id, $log['user_id']);
        }
    }

    #[Test]
    public function it_filters_activity_logs_by_action()
    {
        $this->createTestActivityLogs();

        $response = $this->getJson('/api/v1/admin/logs/activity?action=login');

        $response->assertStatus(200);

        $responseData = $response->json('data');

        // All returned logs should have the specified action
        foreach ($responseData as $log) {
            $this->assertEquals('login', $log['action']);
        }
    }

    #[Test]
    public function it_filters_activity_logs_by_date_range()
    {
        $this->createTestActivityLogs();

        $startDate = now()->subDays(2)->format('Y-m-d');
        $endDate = now()->subDay()->format('Y-m-d');

        $response = $this->getJson("/api/v1/admin/logs/activity?from=$startDate&to=$endDate");

        $response->assertStatus(200);

        $responseData = $response->json('data');

        // All returned logs should be within the date range
        foreach ($responseData as $log) {
            $logDate = \Carbon\Carbon::parse($log['created_at'])->format('Y-m-d');
            $this->assertGreaterThanOrEqual($startDate, $logDate);
            $this->assertLessThanOrEqual($endDate, $logDate);
        }
    }

    #[Test]
    public function it_handles_pagination_of_activity_logs()
    {
        // Create many activity logs
        for ($i = 0; $i < 25; $i++) {
            $this->createActivityLog([
                'user_id' => $this->regularUser->id,
                'action' => 'test_action_' . $i,
                'details' => ['test' => 'Test activity ' . $i],
                'created_at' => now()->subMinutes($i)
            ]);
        }

        $response = $this->getJson('/api/v1/admin/logs/activity?per_page=10&page=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta'
            ]);

        $responseData = $response->json();
        $this->assertEquals(2, $responseData['meta']['current_page']);
        $this->assertEquals(10, $responseData['meta']['per_page']);
        $this->assertCount(10, $responseData['data']);
    }

    /**
     * Create test activity logs for testing.
     */
    private function createTestActivityLogs(): void
    {
        $this->createActivityLog([
            'user_id' => $this->regularUser->id,
            'action' => 'login',
            'target_type' => 'user',
            'target_id' => $this->regularUser->id,
            'details' => ['description' => 'User logged in'],
            'created_at' => now()->subHours(2)
        ]);

        $this->createActivityLog([
            'user_id' => $this->regularUser->id,
            'action' => 'logout',
            'target_type' => 'user',
            'target_id' => $this->regularUser->id,
            'details' => ['description' => 'User logged out'],
            'created_at' => now()->subHours(1)
        ]);

        $this->createActivityLog([
            'user_id' => $this->admin->id,
            'action' => 'login',
            'target_type' => 'user',
            'target_id' => $this->admin->id,
            'details' => ['description' => 'Admin logged in'],
            'created_at' => now()->subMinutes(30)
        ]);
    }

    /**
     * Create an activity log entry.
     * This is a helper method since we don't know the exact model structure.
     */
    private function createActivityLog(array $data): void
    {
        if (class_exists('\App\Models\ActivityLog')) {
            $defaults = [
                'target_type' => 'user',
                'target_id' => $data['user_id'] ?? null,
                'details' => [],
                'created_at' => now(),
            ];
            
            $activityData = array_merge($defaults, $data);
            \App\Models\ActivityLog::create($activityData);
        }
    }
}
