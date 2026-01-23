<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureEmailIsVerifiedTest extends TestCase
{
    
    #[Test]
    public function it_allows_request_when_user_is_not_must_verify_email()
    {
        $middleware = new EnsureEmailIsVerified();
        
        // Create a user that doesn't implement MustVerifyEmail
        $user = new class {
            // User that doesn't implement MustVerifyEmail
            public function hasVerifiedEmail() {
                return true;
            }
        };
        
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals('OK', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_allows_request_when_user_has_verified_email()
    {
        $middleware = new EnsureEmailIsVerified();
        
        $user = new class implements MustVerifyEmail {
            public function hasVerifiedEmail() {
                return true;
            }

            public function markEmailAsVerified() {
                return true;
            }

            public function sendEmailVerificationNotification() {
                // Implementation not needed for test
            }

            public function getEmailForVerification() {
                return 'test@example.com';
            }
        };
        
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals('OK', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_blocks_request_when_user_has_not_verified_email()
    {
        $middleware = new EnsureEmailIsVerified();
        
        $user = new class implements MustVerifyEmail {
            public function hasVerifiedEmail() {
                return false;
            }

            public function markEmailAsVerified() {
                return true;
            }

            public function sendEmailVerificationNotification() {
                // Implementation not needed for test
            }

            public function getEmailForVerification() {
                return 'test@example.com';
            }
        };
        
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $next = function ($req) {
            return response('OK');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals(['message' => 'Your email address is not verified.'], $response->getData(true));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
