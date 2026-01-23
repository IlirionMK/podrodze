<?php

namespace Tests\Unit\Services\Activity;

use App\Models\ActivityLog;
use App\Services\Activity\ActivityMessageFormatter;
use Tests\TestCase;

class ActivityMessageFormatterTest extends TestCase
{
    private ActivityMessageFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ActivityMessageFormatter();
    }

    public function test_format_role_updated()
    {
        $log = new ActivityLog([
            'action' => 'admin.user.role_updated',
            'target_id' => 123,
            'details' => [
                'before' => 'user',
                'after' => 'admin'
            ]
        ]);

        $result = $this->formatter->format($log);
        $this->assertEquals("Admin changed user #123 role from 'user' to 'admin'.", $result);
    }

    public function test_format_role_updated_with_missing_details()
    {
        $log = new ActivityLog([
            'action' => 'admin.user.role_updated',
            'target_id' => 123,
            'details' => []
        ]);

        $result = $this->formatter->format($log);
        $this->assertEquals('Admin updated user role.', $result);
    }

    public function test_format_ban_updated_banned()
    {
        $log = new ActivityLog([
            'action' => 'admin.user.ban_updated',
            'target_id' => 123,
            'details' => ['after' => true]
        ]);

        $result = $this->formatter->format($log);
        $this->assertEquals('Admin banned user #123.', $result);
    }

    public function test_format_ban_updated_unbanned()
    {
        $log = new ActivityLog([
            'action' => 'admin.user.ban_updated',
            'target_id' => 123,
            'details' => ['after' => false]
        ]);

        $result = $this->formatter->format($log);
        $this->assertEquals('Admin unbanned user #123.', $result);
    }

    public function test_format_ban_updated_missing_after()
    {
        $log = new ActivityLog([
            'action' => 'admin.user.ban_updated',
            'target_id' => 123,
            'details' => []
        ]);

        $result = $this->formatter->format($log);
        $this->assertEquals('Admin updated user ban status.', $result);
    }

    public function test_format_unknown_action_with_details()
    {
        $log = new ActivityLog([
            'action' => 'custom.action',
            'details' => ['key' => 'value', 'test' => 123]
        ]);

        $result = $this->formatter->format($log);
        $this->assertEquals('custom.action {"key":"value","test":123}', $result);
    }

    public function test_format_unknown_action_without_details()
    {
        $log = new ActivityLog([
            'action' => 'custom.action',
            'details' => []
        ]);

        $result = $this->formatter->format($log);
        $this->assertEquals('custom.action', $result);
    }
}
