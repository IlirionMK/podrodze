<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureUserIsNotBannedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_allows_request_when_user_is_not_authenticated()
    {
        $middleware = new EnsureUserIsNotBanned();
        $request = new Request();
        
        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function it_allows_request_when_user_is_not_banned()
    {
        $middleware = new EnsureUserIsNotBanned();
        
        $user = $this->createMock(User::class);
        $user->method('isBanned')->willReturn(false);
        $user->method('currentAccessToken')->willReturn(null);
        
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
    public function it_blocks_request_when_user_is_banned_without_token()
    {
        $middleware = new EnsureUserIsNotBanned();
        
        $user = $this->createMock(User::class);
        $user->method('isBanned')->willReturn(true);
        $user->method('currentAccessToken')->willReturn(null);
        
        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(['message' => 'Account is banned.'], $response->getData(true));
    }

    #[Test]
    public function it_blocks_request_and_revokes_token_when_user_is_banned_with_token()
    {
        $middleware = new EnsureUserIsNotBanned();
        
        $token = $this->createMock(PersonalAccessToken::class);
        $token->expects($this->once())->method('delete');

        $user = $this->createMock(User::class);
        $user->method('isBanned')->willReturn(true);
        $user->method('currentAccessToken')->willReturn($token);
        
        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(['message' => 'Account is banned.'], $response->getData(true));
    }

    #[Test]
    public function it_handles_user_without_isbanned_method()
    {
        $middleware = new EnsureUserIsNotBanned();
        
        $user = $this->createMock(\stdClass::class);
        
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
        $bannedUser = User::factory()->create(['banned_at' => now()]);
        $activeUser = User::factory()->create(['banned_at' => null]);

        $middleware = new EnsureUserIsNotBanned();

        // Test with active user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($activeUser) {
            return $activeUser;
        });

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);
        $this->assertEquals('OK', $response->getContent());

        // Test with banned user
        $request->setUserResolver(function () use ($bannedUser) {
            return $bannedUser;
        });

        $response = $middleware->handle($request, $next);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(['message' => 'Account is banned.'], $response->getData(true));
    }

    #[Test]
    public function it_revokes_token_when_banned_user_has_current_access_token()
    {
        $middleware = new EnsureUserIsNotBanned();
        
        // Mock the PersonalAccessToken directly
        $token = $this->createMock(\Laravel\Sanctum\PersonalAccessToken::class);
        $token->expects($this->once())->method('delete');

        // Mock the user to return the token
        $userMock = $this->createMock(User::class);
        $userMock->method('isBanned')->willReturn(true);
        $userMock->method('currentAccessToken')->willReturn($token);
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($userMock) {
            return $userMock;
        });

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(['message' => 'Account is banned.'], $response->getData(true));
    }
}
