<?php

namespace Http\Controllers\Auth;

use App\Http\Controllers\Auth\Auth\AuthenticatedSessionController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    private AuthenticatedSessionController $controller;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AuthenticatedSessionController();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->user->delete();
        parent::tearDown();
    }

    public function test_store_successful_login()
    {
        $request = Mockery::mock(LoginRequest::class);
        $request->shouldReceive('authenticateUser')
            ->once()
            ->andReturn($this->user);

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('user', $response->getData(true));
        $this->assertArrayHasKey('token', $response->getData(true));
    }

    public function test_store_invalid_credentials()
    {
        $request = Mockery::mock(LoginRequest::class);
        $request->shouldReceive('authenticateUser')
            ->once()
            ->andThrow(ValidationException::withMessages(['email' => 'invalid_credentials']));

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals(['message' => 'invalid_credentials'], $response->getData(true));
    }

    public function test_store_banned_user()
    {
        $bannedUser = User::factory()->create([
            'email' => 'banned@example.com',
            'password' => Hash::make('password'),
            'banned_at' => now(),
        ]);

        $request = Mockery::mock(LoginRequest::class);
        $request->shouldReceive('authenticateUser')
            ->once()
            ->andReturn($bannedUser);

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(['message' => 'Account is banned.'], $response->getData(true));

        $bannedUser->delete();
    }

    public function test_store_unverified_email()
    {
        $unverifiedUser = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => null,
        ]);

        $request = Mockery::mock(LoginRequest::class);
        $request->shouldReceive('authenticateUser')
            ->once()
            ->andReturn($unverifiedUser);

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(
            ['message' => 'Your email address is not verified.'],
            $response->getData(true)
        );

        $unverifiedUser->delete();
    }

    public function test_destroy_logs_out_user()
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $request = Request::create('/logout', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $response = $this->controller->destroy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertCount(0, $this->user->tokens);
    }

    public function test_destroy_without_authenticated_user()
    {
        $request = Request::create('/logout', 'POST');

        $response = $this->controller->destroy($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(204, $response->getStatusCode());
    }
}
