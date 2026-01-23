<?php

namespace Http\Controllers\Auth;

use App\Http\Controllers\Auth\Auth\RegisteredUserController;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use DatabaseMigrations;

    private RegisteredUserController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new RegisteredUserController();
        Event::fake();
    }

    public function test_user_registration_success()
    {
        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response = $this->controller->store($request);

        $this->assertEquals(201, $response->getStatusCode());

        $responseData = $response->getData(true);
        $this->assertArrayHasKey('user', $responseData);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertEquals('Test User', $responseData['user']['name']);
        $this->assertEquals('test@example.com', $responseData['user']['email']);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        Event::assertDispatched(Registered::class, function ($event) use ($responseData) {
            return $event->user->id === $responseData['user']['id'];
        });
    }

    public function test_user_registration_validation_fails()
    {
        // Missing required fields
        $request = Request::create('/register', 'POST', []);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->store($request);
    }

    public function test_user_registration_duplicate_email()
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->store($request);
    }

    public function test_user_registration_rate_limiting()
    {
        $ip = '127.0.0.1';
        $throttleKey = 'register|'.$ip;

        // Hit the rate limiter 5 times (the limit)
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey, 60);
        }

        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $request->server->set('REMOTE_ADDR', $ip);

        $response = $this->controller->store($request);

        // 6th attempt should be rate limited
        $this->assertEquals(429, $response->getStatusCode());
        Event::assertDispatched(Lockout::class);
    }

    public function test_password_is_hashed()
    {
        // Use a different email to avoid rate limiting
        $request = Request::create('/register', 'POST', [
            'name' => 'Test User Hash',
            'email' => 'testhash@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $request->server->set('REMOTE_ADDR', '192.168.1.100'); // Different IP

        $response = $this->controller->store($request);

        // First check if the request was successful
        $this->assertEquals(201, $response->getStatusCode());

        $user = User::where('email', 'testhash@example.com')->first();
        $this->assertNotNull($user, 'User should exist in database');
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNotEquals('password', $user->password);
    }
}
