<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\FacebookOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacebookOAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private FacebookOAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FacebookOAuthService();
    }

    #[Test]
    public function it_returns_auth_url()
    {
        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->once()
            ->andReturnSelf();
        $mockProvider->shouldReceive('scopes')
            ->once()
            ->with(['email', 'public_profile'])
            ->andReturnSelf();
        $mockProvider->shouldReceive('redirect')
            ->once()
            ->andReturnSelf();
        $mockProvider->shouldReceive('getTargetUrl')
            ->once()
            ->andReturn('https://facebook.com/oauth/authorize?client_id=test');

        Socialite::shouldReceive('driver')
            ->once()
            ->with('facebook')
            ->andReturn($mockProvider);

        $url = $this->service->getAuthUrl();

        $this->assertEquals('https://facebook.com/oauth/authorize?client_id=test', $url);
    }

    #[Test]
    public function it_authenticates_user_with_valid_code()
    {
        $facebookUser = $this->createMockSocialiteUser([
            'id' => '123456789',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);

        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->twice()
            ->andReturnSelf();
        $mockProvider->shouldReceive('fields')
            ->with(['id', 'name', 'email'])
            ->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->once()
            ->with('valid_code')
            ->andReturn(['access_token' => 'test_token']);
        $mockProvider->shouldReceive('userFromToken')
            ->once()
            ->with('test_token')
            ->andReturn($facebookUser);

        Socialite::shouldReceive('driver')
            ->times(2)
            ->with('facebook')
            ->andReturn($mockProvider);

        $user = $this->service->authenticate('valid_code');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Jane Doe', $user->name);
        $this->assertEquals('jane@example.com', $user->email);
        $this->assertEquals('123456789', $user->facebook_id);
    }

    #[Test]
    public function it_links_existing_user_by_facebook_id()
    {
        $existingUser = User::factory()->create([
            'email' => 'john@example.com',
            'facebook_id' => null,
            'email_verified_at' => null
        ]);

        $facebookUser = $this->createMockSocialiteUser([
            'id' => '123456789',
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')->twice()->andReturnSelf();
        $mockProvider->shouldReceive('fields')->with(['id', 'name', 'email'])->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->once()
            ->andReturn(['access_token' => 'test_token']);
        $mockProvider->shouldReceive('userFromToken')
            ->once()
            ->andReturn($facebookUser);

        Socialite::shouldReceive('driver')
            ->times(2)
            ->with('facebook')
            ->andReturn($mockProvider);

        $user = $this->service->authenticate('valid_code');
        $user->refresh(); // Refresh to get the updated model

        $this->assertEquals($existingUser->id, $user->id);
        $this->assertEquals('123456789', $user->facebook_id);
    }

    #[Test]
    public function it_links_existing_user_by_email()
    {
        $existingUser = User::factory()->create([
            'email' => 'john@example.com',
            'facebook_id' => null,
            'email_verified_at' => null
        ]);

        $facebookUser = $this->createMockSocialiteUser([
            'id' => '123456789',
            'name' => 'John Doe Updated',
            'email' => 'john@example.com'
        ]);

        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')->twice()->andReturnSelf();
        $mockProvider->shouldReceive('fields')->with(['id', 'name', 'email'])->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->once()
            ->andReturn(['access_token' => 'test_token']);
        $mockProvider->shouldReceive('userFromToken')
            ->once()
            ->andReturn($facebookUser);

        Socialite::shouldReceive('driver')
            ->times(2)
            ->with('facebook')
            ->andReturn($mockProvider);

        $user = $this->service->authenticate('valid_code');
        $user->refresh(); // Refresh to get the updated model

        $this->assertEquals($existingUser->id, $user->id);
        $this->assertEquals('123456789', $user->facebook_id);
    }

    #[Test]
    public function it_throws_exception_when_access_token_missing()
    {
        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->once()
            ->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->once()
            ->with('invalid_code')
            ->andReturn([]);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('facebook')
            ->andReturn($mockProvider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Facebook did not return an access token.');

        $this->service->authenticate('invalid_code');
    }

    #[Test]
    public function it_throws_exception_when_email_missing()
    {
        $facebookUser = $this->createMockSocialiteUser([
            'id' => '123456789',
            'name' => 'John Doe',
            'email' => null
        ]);

        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->twice()
            ->andReturnSelf();
        $mockProvider->shouldReceive('fields')
            ->once()
            ->with(['id', 'name', 'email'])
            ->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->once()
            ->andReturn(['access_token' => 'test_token']);
        $mockProvider->shouldReceive('userFromToken')
            ->once()
            ->andReturn($facebookUser);

        Socialite::shouldReceive('driver')
            ->times(2)
            ->with('facebook')
            ->andReturn($mockProvider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('facebook_email_missing');

        $this->service->authenticate('valid_code');
    }

    #[Test]
    public function it_throws_exception_when_user_id_missing()
    {
        $facebookUser = $this->createMockSocialiteUser([
            'id' => null,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $mockProvider = Mockery::mock();
        $mockProvider->shouldReceive('stateless')
            ->twice()
            ->andReturnSelf();
        $mockProvider->shouldReceive('fields')
            ->once()
            ->with(['id', 'name', 'email'])
            ->andReturnSelf();
        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->once()
            ->andReturn(['access_token' => 'test_token']);
        $mockProvider->shouldReceive('userFromToken')
            ->once()
            ->andReturn($facebookUser);

        Socialite::shouldReceive('driver')
            ->times(2)
            ->with('facebook')
            ->andReturn($mockProvider);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Facebook did not return a valid user ID.');

        $this->service->authenticate('valid_code');
    }

    private function createMockSocialiteUser(array $data): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        
        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getName')->andReturn($name);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getRaw')->andReturn(array_merge([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ], $data));
        
        return $user;
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Close Mockery
        if ($container = Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
    }
}
