<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_blocks_request_when_user_is_not_authenticated()
    {
        $middleware = new EnsureUserIsAdmin();
        $request = new Request();
        
        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(['message' => 'Forbidden.'], $response->getData(true));
    }

    #[Test]
    public function it_blocks_request_when_user_is_not_admin()
    {
        $middleware = new EnsureUserIsAdmin();
        
        $user = $this->createMock(User::class);
        $user->method('isAdmin')->willReturn(false);
        
        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(['message' => 'Forbidden.'], $response->getData(true));
    }

    #[Test]
    public function it_allows_request_when_user_is_admin()
    {
        $middleware = new EnsureUserIsAdmin();
        
        $user = $this->createMock(User::class);
        $user->method('isAdmin')->willReturn(true);
        
        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function it_works_with_real_user_model()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $regularUser = User::factory()->create(['role' => 'user']);

        $middleware = new EnsureUserIsAdmin();

        // Test with admin user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($adminUser) {
            return $adminUser;
        });

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);
        $this->assertEquals('OK', $response->getContent());

        // Test with regular user
        $request->setUserResolver(function () use ($regularUser) {
            return $regularUser;
        });

        $response = $middleware->handle($request, $next);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
