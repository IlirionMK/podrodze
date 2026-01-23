<?php

namespace Tests\Unit\Jobs;

use App\Jobs\TestRedisJob;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestRedisJobTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated()
    {
        $job = new TestRedisJob();
        
        $this->assertInstanceOf(TestRedisJob::class, $job);
    }

    #[Test]
    public function it_logs_redis_queue_success_message()
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, '✅ Redis queue works at');
            });

        $job = new TestRedisJob();
        $job->handle();
    }

    #[Test]
    public function it_includes_current_timestamp_in_log_message()
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, '✅ Redis queue works at') && 
                       str_contains($message, now()->format('Y-m-d'));
            });

        $job = new TestRedisJob();
        $job->handle();
    }

    #[Test]
    public function it_has_queueable_trait()
    {
        $job = new TestRedisJob();
        
        $this->assertTrue(method_exists($job, 'onQueue'));
        $this->assertTrue(method_exists($job, 'delay'));
        $this->assertTrue(method_exists($job, 'chain'));
    }

    #[Test]
    public function it_implements_should_queue_interface()
    {
        $job = new TestRedisJob();
        
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }
}
