<?php

namespace Tests\Unit\Models;

use App\Models\ActivityLog;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\ModelTestCase;

class ActivityLogTest extends ModelTestCase
{
    #[Test]
    public function it_has_required_fields()
    {
        $log = $this->createActivityLog([
            'action' => 'test_action',
        ]);

        $this->assertEquals('test_action', $log->action);
        $this->assertNotNull($log->user_id);
    }

    #[Test]
    public function it_belongs_to_user()
    {
        $user = $this->createUser();
        $log = $this->createActivityLog(['user_id' => $user->id]);

        $this->assertEquals($user->id, $log->user_id);
    }

    #[Test]
    public function it_casts_details()
    {
        $testData = ['key' => 'value'];
        $log = $this->createActivityLog(['details' => $testData]);
        $this->assertIsArray($log->details);
        $this->assertEquals($testData, $log->details);
    }
}
