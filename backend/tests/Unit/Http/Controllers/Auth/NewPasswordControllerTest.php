<?php

namespace Http\Controllers\Auth;

use App\Http\Controllers\Auth\Auth\NewPasswordController;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NewPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    private NewPasswordController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new NewPasswordController();
    }

    public function test_password_reset_success()
    {
        Event::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password')
        ]);

        $token = Password::createToken($user);

        $request = Request::create('/reset-password', 'POST', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response = $this->controller->store($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            ['status' => __('Your password has been reset.')],
            $response->getData(true)
        );

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));

        Event::assertDispatched(PasswordReset::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_password_reset_invalid_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $request = Request::create('/reset-password', 'POST', [
            'token' => 'invalid-token',
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This password reset token is invalid.');

        $this->controller->store($request);
    }

    public function test_password_reset_validation_fails()
    {
        $request = Request::create('/reset-password', 'POST', [
            // Missing required fields
        ]);

        $this->expectException(ValidationException::class);

        $this->controller->store($request);
    }

    public function test_password_reset_password_mismatch()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $request = Request::create('/reset-password', 'POST', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $this->expectException(ValidationException::class);

        $this->controller->store($request);
    }
}
