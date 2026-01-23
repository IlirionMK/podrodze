<?php

namespace Http\Controllers\Auth;

use App\Http\Controllers\Auth\Auth\GoogleAuthController;
use App\Http\Requests\Auth\GoogleCallbackRequest;
use App\Models\User;
use App\Services\Auth\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Mockery;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    private GoogleAuthController $controller;
    private GoogleOAuthService $googleOAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->googleOAuth = Mockery::mock(GoogleOAuthService::class);
        $this->controller = new GoogleAuthController($this->googleOAuth);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_auth_url()
    {
        $authUrl = 'https://accounts.google.com/o/oauth2/auth';

        $this->googleOAuth->shouldReceive('getAuthUrl')
            ->once()
            ->andReturn($authUrl);

        $response = $this->controller->getAuthUrl();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['url' => $authUrl], $response->getData(true));
    }

    public function test_handle_callback_success()
    {
        $user = User::factory()->create();
        $token = 'test-token';
        $code = 'test-code';

        $request = Mockery::mock(GoogleCallbackRequest::class);
        $request->code = $code;

        $this->googleOAuth->shouldReceive('authenticate')
            ->with($code)
            ->once()
            ->andReturn($user);

        $response = $this->controller->handleCallback($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = $response->getData(true);
        $this->assertArrayHasKey('user', $responseData);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertEquals($user->id, $responseData['user']['id']);
    }

    public function test_handle_callback_failure()
    {
        $code = 'invalid-code';
        $errorMessage = 'Invalid code';

        $request = Mockery::mock(GoogleCallbackRequest::class);
        $request->code = $code;

        $this->googleOAuth->shouldReceive('authenticate')
            ->with($code)
            ->once()
            ->andThrow(new \Exception($errorMessage));

        $response = $this->controller->handleCallback($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $responseData = $response->getData(true);
        $this->assertEquals('Google OAuth failed', $responseData['message']);
        $this->assertEquals($errorMessage, $responseData['error']);
    }
}
