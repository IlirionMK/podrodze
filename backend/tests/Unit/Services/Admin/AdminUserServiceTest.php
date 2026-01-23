<?php

namespace Tests\Unit\Services\Admin;

use App\Models\User;
use App\Services\Admin\AdminUserService;
use App\Services\Activity\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminUserService $service;
    private ActivityLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(ActivityLogger::class);
        $this->service = new AdminUserService($this->logger);
    }

    #[Test]
    public function it_paginates_users_without_search(): void
    {
        // Create test users
        User::factory()->count(25)->create();

        $result = $this->service->paginateUsers('', 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(10, $result->items());
        $this->assertEquals(25, $result->total());
    }

    #[Test]
    public function it_paginates_users_with_search_by_name(): void
    {
        User::factory()->create(['name' => 'John Doe']);
        User::factory()->create(['name' => 'Jane Smith']);
        User::factory()->create(['name' => 'Bob Johnson']);

        $result = $this->service->paginateUsers('John Doe', 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('John Doe', $result->items()[0]->name);
    }

    #[Test]
    public function it_paginates_users_with_search_by_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);
        User::factory()->create(['email' => 'jane@example.com']);
        User::factory()->create(['email' => 'bob@example.com']);

        $result = $this->service->paginateUsers('jane', 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('jane@example.com', $result->items()[0]->email);
    }

    #[Test]
    public function it_paginates_users_with_search_partial_match(): void
    {
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Johnny Smith', 'email' => 'johnny@example.com']);

        $result = $this->service->paginateUsers('john', 10);

        $this->assertCount(2, $result->items());
    }

    #[Test]
    public function it_handles_whitespace_in_search(): void
    {
        User::factory()->count(3)->create();

        $result = $this->service->paginateUsers('   ', 10);

        $this->assertCount(3, $result->items());
    }

    #[Test]
    public function it_sets_user_role_successfully(): void
    {
        $target = User::factory()->create(['role' => 'user']);
        $actor = User::factory()->create(['role' => 'admin']);

        $this->logger->shouldReceive('add')
            ->once()
            ->with($actor, 'admin.user.role_updated', $target, [
                'before' => 'user',
                'after' => 'admin',
            ]);

        $result = $this->service->setRole($target, 'admin', $actor);

        $this->assertTrue($result['ok']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($target->id, $result['payload']['data']['id']);
        $this->assertEquals('admin', $result['payload']['data']['role']);
        
        $target->refresh();
        $this->assertEquals('admin', $target->role);
    }

    #[Test]
    public function it_sets_user_role_without_actor(): void
    {
        $target = User::factory()->create(['role' => 'user']);

        $this->logger->shouldReceive('add')
            ->once()
            ->with(null, 'admin.user.role_updated', $target, [
                'before' => 'user',
                'after' => 'admin',
            ]);

        $result = $this->service->setRole($target, 'admin', null);

        $this->assertTrue($result['ok']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('admin', $result['payload']['data']['role']);
        
        $target->refresh();
        $this->assertEquals('admin', $target->role);
    }

    #[Test]
    public function it_prevents_user_from_removing_own_admin_role(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);

        $this->logger->shouldNotReceive('add');

        $result = $this->service->setRole($actor, 'user', $actor);

        $this->assertFalse($result['ok']);
        $this->assertEquals(422, $result['status']);
        $this->assertEquals('You cannot remove your own admin role.', $result['payload']['message']);
        
        $actor->refresh();
        $this->assertEquals('admin', $actor->role); // Role unchanged
    }

    #[Test]
    public function it_allows_user_to_change_own_role_to_admin(): void
    {
        $actor = User::factory()->create(['role' => 'user']);

        $this->logger->shouldReceive('add')
            ->once()
            ->with($actor, 'admin.user.role_updated', $actor, [
                'before' => 'user',
                'after' => 'admin',
            ]);

        $result = $this->service->setRole($actor, 'admin', $actor);

        $this->assertTrue($result['ok']);
        $this->assertEquals(200, $result['status']);
        
        $actor->refresh();
        $this->assertEquals('admin', $actor->role);
    }

    #[Test]
    public function it_bans_user_successfully(): void
    {
        $target = User::factory()->create(['banned_at' => null]);
        $actor = User::factory()->create(['role' => 'admin']);

        $this->logger->shouldReceive('add')
            ->once()
            ->with($actor, 'admin.user.ban_updated', $target, [
                'before' => false,
                'after' => true,
            ]);

        $result = $this->service->setBanned($target, true, $actor);

        $this->assertTrue($result['ok']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($target->id, $result['payload']['data']['id']);
        $this->assertTrue($result['payload']['data']['banned']);
        $this->assertNotNull($result['payload']['data']['banned_at']);
        
        $target->refresh();
        $this->assertNotNull($target->banned_at);
    }

    #[Test]
    public function it_unbans_user_successfully(): void
    {
        $target = User::factory()->create(['banned_at' => now()->subDays(1)]);
        $actor = User::factory()->create(['role' => 'admin']);

        $this->logger->shouldReceive('add')
            ->once()
            ->with($actor, 'admin.user.ban_updated', $target, [
                'before' => true,
                'after' => false,
            ]);

        $result = $this->service->setBanned($target, false, $actor);

        $this->assertTrue($result['ok']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($target->id, $result['payload']['data']['id']);
        $this->assertFalse($result['payload']['data']['banned']);
        $this->assertNull($result['payload']['data']['banned_at']);
        
        $target->refresh();
        $this->assertNull($target->banned_at);
    }

    #[Test]
    public function it_bans_user_without_actor(): void
    {
        $target = User::factory()->create(['banned_at' => null]);

        $this->logger->shouldReceive('add')
            ->once()
            ->with(null, 'admin.user.ban_updated', $target, [
                'before' => false,
                'after' => true,
            ]);

        $result = $this->service->setBanned($target, true, null);

        $this->assertTrue($result['ok']);
        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['payload']['data']['banned']);
        
        $target->refresh();
        $this->assertNotNull($target->banned_at);
    }

    #[Test]
    public function it_prevents_user_from_banning_own_account(): void
    {
        $actor = User::factory()->create(['banned_at' => null]);

        $this->logger->shouldNotReceive('add');

        $result = $this->service->setBanned($actor, true, $actor);

        $this->assertFalse($result['ok']);
        $this->assertEquals(422, $result['status']);
        $this->assertEquals('You cannot ban your own account.', $result['payload']['message']);
        
        $actor->refresh();
        $this->assertNull($actor->banned_at); // Still not banned
    }

    #[Test]
    public function it_allows_user_to_unban_own_account(): void
    {
        $actor = User::factory()->create(['banned_at' => now()->subDays(1)]);

        $this->logger->shouldReceive('add')
            ->once()
            ->with($actor, 'admin.user.ban_updated', $actor, [
                'before' => true,
                'after' => false,
            ]);

        $result = $this->service->setBanned($actor, false, $actor);

        $this->assertTrue($result['ok']);
        $this->assertEquals(200, $result['status']);
        
        $actor->refresh();
        $this->assertNull($actor->banned_at);
    }

    #[Test]
    public function it_deletes_user_tokens_when_banning(): void
    {
        $target = User::factory()->create(['banned_at' => null]);
        $actor = User::factory()->create(['role' => 'admin']);

        // Create some tokens for the user
        $token1 = $target->createToken('test1');
        $token2 = $target->createToken('test2');

        $this->assertEquals(2, $target->tokens()->count());

        $this->logger->shouldReceive('add')
            ->once()
            ->with($actor, 'admin.user.ban_updated', $target, [
                'before' => false,
                'after' => true,
            ]);

        $this->service->setBanned($target, true, $actor);

        $target->refresh();
        $this->assertEquals(0, $target->tokens()->count());
    }

    #[Test]
    public function it_does_not_delete_tokens_when_unbanning(): void
    {
        $target = User::factory()->create(['banned_at' => now()->subDays(1)]);
        $actor = User::factory()->create(['role' => 'admin']);

        // Create tokens after user is already banned
        $token = $target->createToken('test');

        $this->assertEquals(1, $target->tokens()->count());

        $this->logger->shouldReceive('add')
            ->once()
            ->with($actor, 'admin.user.ban_updated', $target, [
                'before' => true,
                'after' => false,
            ]);

        $this->service->setBanned($target, false, $actor);

        $target->refresh();
        $this->assertEquals(1, $target->tokens()->count()); // Token still exists
    }

    #[Test]
    public function it_returns_correct_banned_at_format(): void
    {
        $target = User::factory()->create(['banned_at' => null]);
        $actor = User::factory()->create(['role' => 'admin']);

        $this->logger->shouldReceive('add')->once();

        $result = $this->service->setBanned($target, true, $actor);

        $this->assertNotNull($result['payload']['data']['banned_at']);
        $this->assertIsString($result['payload']['data']['banned_at']);
        
        // Check if it's a valid ISO 8601 date format (more flexible pattern)
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?Z?$/',
            $result['payload']['data']['banned_at']
        );
    }

    #[Test]
    public function it_returns_null_banned_at_when_unbanned(): void
    {
        $target = User::factory()->create(['banned_at' => now()->subDays(1)]);
        $actor = User::factory()->create(['role' => 'admin']);

        $this->logger->shouldReceive('add')->once();

        $result = $this->service->setBanned($target, false, $actor);

        $this->assertNull($result['payload']['data']['banned_at']);
    }
}
