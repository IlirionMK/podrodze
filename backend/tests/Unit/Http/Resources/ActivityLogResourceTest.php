<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Activity\ActivityMessageFormatter;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityLogResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_activity_log_to_array(): void
    {
        $user = User::factory()->create();

        $activityLog = ActivityLog::factory()->create([
            'id' => 1,
            'user_id' => $user->id,
            'action' => 'trip.created',
            'target_type' => 'trip',
            'target_id' => 123,
            'details' => ['trip_name' => 'Test Trip'],
            'created_at' => '2025-01-22 20:00:00',
        ]);

        // Mock the formatter
        $formatterMock = Mockery::mock(ActivityMessageFormatter::class);
        $formatterMock->shouldReceive('format')
            ->once()
            ->with($activityLog)
            ->andReturn('User created trip "Test Trip"');

        $this->app->instance(ActivityMessageFormatter::class, $formatterMock);

        $resource = new ActivityLogResource($activityLog);
        $result = $resource->toArray(request());

        $this->assertEquals(1, $result['id']);
        $this->assertEquals($user->id, $result['user_id']);
        $this->assertEquals('trip.created', $result['action']);
        $this->assertEquals('trip', $result['target_type']);
        $this->assertEquals(123, $result['target_id']);
        $this->assertEquals(['trip_name' => 'Test Trip'], $result['details']);
        $this->assertEquals('User created trip "Test Trip"', $result['message']);
        $this->assertEquals('2025-01-22T20:00:00.000000Z', $result['created_at']);
    }

    #[Test]
    public function it_handles_null_created_at(): void
    {
        $activityLog = ActivityLog::factory()->create([
            'created_at' => null,
        ]);

        // Mock the formatter
        $formatterMock = Mockery::mock(ActivityMessageFormatter::class);
        $formatterMock->shouldReceive('format')
            ->once()
            ->andReturn('Test message');

        $this->app->instance(ActivityMessageFormatter::class, $formatterMock);

        $resource = new ActivityLogResource($activityLog);
        $result = $resource->toArray(request());

        $this->assertNull($result['created_at']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $activityLog = ActivityLog::factory()->create();

        // Mock the formatter
        $formatterMock = Mockery::mock(ActivityMessageFormatter::class);
        $formatterMock->shouldReceive('format')
            ->once()
            ->andReturn('Test message');

        $this->app->instance(ActivityMessageFormatter::class, $formatterMock);

        $resource = new ActivityLogResource($activityLog);
        $result = $resource->toArray(request());

        $expectedKeys = ['id', 'user_id', 'action', 'target_type', 'target_id', 'details', 'message', 'created_at'];
        $this->assertEquals($expectedKeys, array_keys($result));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
