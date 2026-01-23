<?php

namespace Http\Controllers\Auth;

use App\Http\Controllers\Auth\FacebookAuthController;
use App\Http\Requests\Auth\FacebookCallbackRequest;
use App\Models\User;
use App\Services\Auth\FacebookOAuthService;
use Illuminate\Http\JsonResponse;
use Mockery;
use Tests\TestCase;

class FacebookAuthControllerTest extends TestCase
{
    private FacebookAuthController $controller;
    private FacebookOAuthService $facebookOAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facebookOAuth = Mockery::mock(FacebookOAuthService::class);
        $this->controller = new FacebookAuthController($this->facebookOAuth);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_auth_url()
    {
        $authUrl = 'https://facebook.com/login/oauth/authorize';

        $this->facebookOAuth->shouldReceive('getAuthUrl')
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

        $request = Mockery::mock(FacebookCallbackRequest::class);
        $request->code = $code;

        $this->facebookOAuth->shouldReceive('authenticate')
            ->with($code)
            ->once()
            ->andReturn($user);

        $response = $this->controller->handleCallback($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('user', $responseData);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertIsString($responseData['token']);
        
        $user->delete();
    }

    public function test_handle_callback_missing_email()
    {
        $code = 'test-code';

        $request = Mockery::mock(FacebookCallbackRequest::class);
        $request->code = $code;

        $this->facebookOAuth->shouldReceive('authenticate')
            ->with($code)
            ->once()
            ->andThrow(new \Exception('facebook_email_missing'));

        $response = $this->controller->handleCallback($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals(
            [
                'message' => 'Facebook OAuth failed',
                'error' => 'facebook_email_missing',
            ],
            $response->getData(true)
        );
    }

    public function test_handle_callback_generic_error()
    {
        $code = 'test-code';
        $errorMessage = 'Some Facebook API error';

        $request = Mockery::mock(FacebookCallbackRequest::class);
        $request->code = $code;

        $this->facebookOAuth->shouldReceive('authenticate')
            ->with($code)
            ->once()
            ->andThrow(new \Exception($errorMessage));

        $response = $this->controller->handleCallback($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals(
            [
                'message' => 'Facebook OAuth failed',
                'error' => $errorMessage,
            ],
            $response->getData(true)
        );
    }
}
