<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleOAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private GoogleOAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoogleOAuthService();
    }

    #[Test]
    public function it_returns_auth_url()
    {
        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->once()
            ->andReturnSelf();
        $mockProvider->shouldReceive('redirect')
            ->once()
            ->andReturnSelf();
        $mockProvider->shouldReceive('getTargetUrl')
            ->once()
            ->andReturn('https://google.com/auth/url');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($mockProvider);

        $url = $this->service->getAuthUrl();

        $this->assertEquals('https://google.com/auth/url', $url);
    }

    #[Test]
    public function it_throws_exception_when_access_token_missing()
    {
        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->once()
            ->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->with('invalid_code')
            ->once()
            ->andReturn([]);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($mockProvider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google did not return an access token.');

        $this->service->authenticate('invalid_code');
    }

    #[Test]
    public function it_throws_exception_when_user_id_missing()
    {
        // Create a mock user with no ID or sub field
        $googleUser = new \Laravel\Socialite\Two\User();
        $googleUser->id = null;
        $googleUser->email = 'test@example.com';
        $googleUser->name = 'Test User';
        $googleUser->user = [
            'email' => 'test@example.com',
            'name' => 'Test User',
            // No 'sub' field to trigger the exception
        ];

        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->twice()
            ->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->with('valid_code')
            ->once()
            ->andReturn(['access_token' => 'test_token']);
        $mockProvider->shouldReceive('userFromToken')
            ->with('test_token')
            ->once()
            ->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($mockProvider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google did not return a valid user ID.');

        $this->service->authenticate('valid_code');
    }

    #[Test]
    public function it_handles_token_exchange_failure()
    {
        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->once()
            ->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->with('invalid_code')
            ->once()
            ->andThrow(new \Exception('Invalid code'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($mockProvider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to exchange authorization code: Invalid code');

        $this->service->authenticate('invalid_code');
    }

    #[Test]
    public function it_handles_user_fetch_failure()
    {
        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->twice()  // Changed from once() to twice() since it's called twice
            ->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->with('valid_code')
            ->once()
            ->andReturn(['access_token' => 'test_token']);
        $mockProvider->shouldReceive('userFromToken')
            ->with('test_token')
            ->once()
            ->andThrow(new \Exception('User not found'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($mockProvider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch Google user: User not found');

        $this->service->authenticate('valid_code');
    }

    protected function createMockSocialiteUser(array $data): \Laravel\Socialite\AbstractUser
    {
        // Create a mock of the Socialite User
        $user = Mockery::mock(\Laravel\Socialite\Two\User::class)->makePartial();

        // Set default values
        $defaults = [
            'sub' => '123456789',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified' => true,
            'given_name' => 'Test',
            'family_name' => 'User',
            'picture' => null,
            'locale' => 'en',
        ];

        // Merge with provided data
        $userData = array_merge($defaults, $data);

        // Set up the user array that Socialite uses internally
        $userArray = [
            'sub' => $userData['sub'],
            'name' => $userData['name'],
            'email' => $userData['email'],
            'email_verified' => $userData['email_verified'],
            'given_name' => $userData['given_name'],
            'family_name' => $userData['family_name'],
            'picture' => $userData['picture'],
            'locale' => $userData['locale'],
        ];

        // Set up the mock methods
        $user->shouldReceive('getId')->andReturn($userData['sub']);
        $user->shouldReceive('getEmail')->andReturn($userData['email']);
        $user->shouldReceive('getName')->andReturn($userData['name']);
        $user->shouldReceive('getEmailVerified')->andReturn($userData['email_verified']);

        // Make the user array accessible
        $user->user = $userArray;

        // Set the email_verified property directly on the mock object
        // This is needed because the service accesses $googleUser->email_verified directly
        $user->email_verified = $userData['email_verified'];

        return $user;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
