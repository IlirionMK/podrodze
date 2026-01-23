<?php

namespace Tests\Unit\Services\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    private ActivityLogger $activityLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityLogger = new ActivityLogger();
    }

    public function test_add_activity_with_user_and_model_target()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->activityLogger->add($user, 'user.updated', $targetUser, ['field' => 'name']);
        
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'user.updated',
            'target_type' => $targetUser->getMorphClass(),
            'target_id' => $targetUser->id,
        ]);
    }

    public function test_add_activity_with_string_target()
    {
        $user = User::factory()->create();
        $target = 'custom_target';
        
        $this->activityLogger->add($user, 'system.event', $target, ['data' => 'test']);
        
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'system.event',
            'target_type' => 'custom_target',
            'target_id' => null,
        ]);
    }

    public function test_add_activity_with_null_target()
    {
        $user = User::factory()->create();
        
        $this->activityLogger->add($user, 'system.start', null, ['info' => 'test']);
        
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'system.start',
            'target_type' => null,
            'target_id' => null,
        ]);
    }

    public function test_add_activity_with_non_model_actor()
    {
        $actor = 'system';
        
        $this->activityLogger->add($actor, 'system.event', null, ['info' => 'test']);
        
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'action' => 'system.event',
            'target_type' => null,
            'target_id' => null,
        ]);
    }
}
