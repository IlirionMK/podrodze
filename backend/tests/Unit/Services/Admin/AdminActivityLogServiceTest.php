<?php

namespace Tests\Unit\Services\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminActivityLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminActivityLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminActivityLogService();
    }

    #[Test]
    public function it_paginates_activity_logs_without_filters(): void
    {
        // Create test data
        ActivityLog::factory()->count(15)->create();

        $result = $this->service->paginate([], 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(10, $result->items());
        $this->assertEquals(15, $result->total());
    }

    #[Test]
    public function it_applies_date_range_filters(): void
    {
        // Create test data with different dates
        ActivityLog::factory()->create(['created_at' => '2024-01-01 10:00:00']);
        ActivityLog::factory()->create(['created_at' => '2024-01-05 10:00:00']);
        ActivityLog::factory()->create(['created_at' => '2024-01-10 10:00:00']);

        $filters = [
            'from' => '2024-01-03',
            'to' => '2024-01-07'
        ];

        $result = $this->service->paginate($filters, 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('2024-01-05 10:00:00', $result->items()[0]->created_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_applies_user_id_filter(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        ActivityLog::factory()->create(['user_id' => $user1->id]);
        ActivityLog::factory()->create(['user_id' => $user2->id]);
        ActivityLog::factory()->create(['user_id' => $user1->id]);

        $filters = ['user_id' => $user1->id];

        $result = $this->service->paginate($filters, 10);

        $this->assertCount(2, $result->items());
        collect($result->items())->each(function ($log) use ($user1) {
            $this->assertEquals($user1->id, $log->user_id);
        });
    }

    #[Test]
    public function it_applies_action_filter(): void
    {
        ActivityLog::factory()->create(['action' => 'user.created']);
        ActivityLog::factory()->create(['action' => 'user.updated']);
        ActivityLog::factory()->create(['action' => 'user.deleted']);

        $filters = ['action' => 'user.updated'];

        $result = $this->service->paginate($filters, 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('user.updated', $result->items()[0]->action);
    }

    #[Test]
    public function it_applies_search_filter_to_action(): void
    {
        ActivityLog::factory()->create(['action' => 'user.created']);
        ActivityLog::factory()->create(['action' => 'trip.created']);
        ActivityLog::factory()->create(['action' => 'user.updated']);

        $filters = ['search' => 'user.created'];

        $result = $this->service->paginate($filters, 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('user.created', $result->items()[0]->action);
    }

    #[Test]
    public function it_applies_search_filter_to_target_type(): void
    {
        ActivityLog::factory()->create(['target_type' => 'User']);
        ActivityLog::factory()->create(['target_type' => 'Trip']);
        ActivityLog::factory()->create(['target_type' => 'User']);

        $filters = ['search' => 'Trip'];

        $result = $this->service->paginate($filters, 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('Trip', $result->items()[0]->target_type);
    }

    #[Test]
    public function it_applies_search_filter_to_details_mysql(): void
    {
        // Test with actual database connection (using SQLite in testing)
        // We'll test the logic by checking the SQL generated
        ActivityLog::factory()->create(['details' => ['message' => 'User John Doe was created']]);
        ActivityLog::factory()->create(['details' => ['message' => 'Trip to Warsaw was created']]);
        ActivityLog::factory()->create(['details' => ['message' => 'User Jane Smith was updated']]);

        $filters = ['search' => 'John'];

        $result = $this->service->paginate($filters, 10);

        // Should find the record with 'John' in the details
        $this->assertCount(1, $result->items());
        $this->assertStringContainsString('John', $result->items()[0]->details['message']);
    }

    #[Test]
    public function it_applies_search_filter_to_details_postgresql(): void
    {
        // Test with actual database connection (using SQLite in testing)
        ActivityLog::factory()->create(['details' => ['message' => 'User John Doe was created']]);
        ActivityLog::factory()->create(['details' => ['message' => 'User Jane Smith was updated']]);

        $filters = ['search' => 'Jane'];

        $result = $this->service->paginate($filters, 10);

        // Should find the record with 'Jane' in the details
        $this->assertCount(1, $result->items());
        $this->assertStringContainsString('Jane', $result->items()[0]->details['message']);
    }

    #[Test]
    public function it_applies_search_filter_to_details_other_database(): void
    {
        // Test with actual database connection (using SQLite in testing)
        ActivityLog::factory()->create(['details' => ['message' => 'User John Doe was created']]);
        ActivityLog::factory()->create(['details' => ['message' => 'User Jane Smith was updated']]);

        $filters = ['search' => 'Jane'];

        $result = $this->service->paginate($filters, 10);

        // Should find the record with 'Jane' in the details
        $this->assertCount(1, $result->items());
        $this->assertStringContainsString('Jane', $result->items()[0]->details['message']);
    }

    #[Test]
    public function it_handles_missing_created_at_column_gracefully(): void
    {
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'created_at')
            ->andReturn(false);
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'id')
            ->andReturn(true);

        ActivityLog::factory()->count(3)->create();

        $filters = ['from' => '2024-01-01', 'to' => '2024-01-31'];

        $result = $this->service->paginate($filters, 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result->items());
    }

    #[Test]
    public function it_handles_missing_user_id_column_gracefully(): void
    {
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'created_at')
            ->andReturn(true);
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'user_id')
            ->andReturn(false);
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'action')
            ->andReturn(true);
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'id')
            ->andReturn(true);

        ActivityLog::factory()->count(3)->create();

        $filters = ['user_id' => 1];

        $result = $this->service->paginate($filters, 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result->items());
    }

    #[Test]
    public function it_handles_missing_action_column_gracefully(): void
    {
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'created_at')
            ->andReturn(true);
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'user_id')
            ->andReturn(false);
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'action')
            ->andReturn(false);
        Schema::shouldReceive('hasColumn')
            ->with('activity_logs', 'id')
            ->andReturn(true);

        ActivityLog::factory()->count(3)->create();

        $filters = ['action' => 'user.created'];

        $result = $this->service->paginate($filters, 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result->items());
    }

    #[Test]
    public function it_orders_by_id_by_default(): void
    {
        $log1 = ActivityLog::factory()->create(['id' => 3]);
        $log2 = ActivityLog::factory()->create(['id' => 1]);
        $log3 = ActivityLog::factory()->create(['id' => 2]);

        $result = $this->service->paginate([], 10);

        $items = $result->items();
        $this->assertEquals(3, $items[0]->id);
        $this->assertEquals(2, $items[1]->id);
        $this->assertEquals(1, $items[2]->id);
    }

    #[Test]
    public function it_orders_by_created_at_when_id_column_missing(): void
    {
        Schema::shouldReceive('hasColumn')->with('activity_logs', 'id')->andReturn(false);
        Schema::shouldReceive('hasColumn')->with('activity_logs', 'created_at')->andReturn(true);

        $log1 = ActivityLog::factory()->create(['created_at' => '2024-01-01']);
        $log2 = ActivityLog::factory()->create(['created_at' => '2024-01-03']);
        $log3 = ActivityLog::factory()->create(['created_at' => '2024-01-02']);

        $result = $this->service->paginate([], 10);

        $items = $result->items();
        $this->assertEquals('2024-01-03', $items[0]->created_at->format('Y-m-d'));
        $this->assertEquals('2024-01-02', $items[1]->created_at->format('Y-m-d'));
        $this->assertEquals('2024-01-01', $items[2]->created_at->format('Y-m-d'));
    }

    #[Test]
    public function it_handles_empty_search_gracefully(): void
    {
        ActivityLog::factory()->count(3)->create();

        $filters = ['search' => '   ']; // Whitespace only

        $result = $this->service->paginate($filters, 10);

        $this->assertCount(3, $result->items());
    }

    #[Test]
    public function it_handles_multiple_filters_combined(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $log1 = ActivityLog::factory()->create([
            'user_id' => $user1->id,
            'action' => 'user.created',
            'target_type' => 'User',
            'created_at' => '2024-01-05'
        ]);

        $log2 = ActivityLog::factory()->create([
            'user_id' => $user2->id,
            'action' => 'user.created',
            'target_type' => 'User',
            'created_at' => '2024-01-06'
        ]);

        $log3 = ActivityLog::factory()->create([
            'user_id' => $user1->id,
            'action' => 'trip.created',
            'target_type' => 'Trip',
            'created_at' => '2024-01-04'
        ]);

        $filters = [
            'user_id' => $user1->id,
            'action' => 'user.created',
            'from' => '2024-01-01',
            'to' => '2024-01-31'
        ];

        $result = $this->service->paginate($filters, 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals($log1->id, $result->items()[0]->id);
    }
}
