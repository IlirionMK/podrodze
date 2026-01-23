<?php

namespace Http\Controllers\Auth;

use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    private PasswordResetLinkController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new PasswordResetLinkController();
    }

    public function test_send_reset_link_success()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'test@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);

        $request = Request::create('/forgot-password', 'POST', [
            'email' => 'test@example.com',
        ]);

        $response = $this->controller->store($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('status', $response->getData(true));
    }

    public function test_send_reset_link_invalid_email()
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'nonexistent@example.com'])
            ->andReturn(Password::INVALID_USER);

        $request = Request::create('/forgot-password', 'POST', [
            'email' => 'nonexistent@example.com',
        ]);

        $response = $this->controller->store($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('status', $response->getData(true));
    }

    public function test_send_reset_link_validation_fails()
    {
        $request = Request::create('/forgot-password', 'POST', [
            // Missing email
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->controller->store($request);
    }

    public function test_send_reset_link_throttled()
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'test@example.com'])
            ->andReturn(Password::RESET_THROTTLED);

        $request = Request::create('/forgot-password', 'POST', [
            'email' => 'test@example.com',
        ]);

        $response = $this->controller->store($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('status', $response->getData(true));
    }
}
