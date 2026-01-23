<?php

namespace Tests\Unit\Http\Requests\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    use RefreshDatabase;

    private LoginRequest $request;
    private string $testEmail = 'test@example.com';
    private string $testPassword = 'password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new LoginRequest();

        // Clear rate limiter before each test
        RateLimiter::clear($this->throttleKey());
    }

    private function throttleKey(): string
    {
        return Str::lower($this->testEmail).'|'."127.0.0.1";
    }

    #[Test]
    public function it_authorizes_request(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    #[Test]
    public function it_returns_correct_validation_rules(): void
    {
        $expectedRules = [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];

        $this->assertEquals($expectedRules, $this->request->rules());
    }

    #[Test]
    public function it_authenticates_user_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => $this->testEmail,
            'password' => Hash::make($this->testPassword),
        ]);

        $request = new LoginRequest();
        $request->merge([
            'email' => $this->testEmail,
            'password' => $this->testPassword,
        ]);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->validateResolved();

        $authenticatedUser = $request->authenticateUser();

        $this->assertTrue($authenticatedUser->is($user));
    }

    #[Test]
    public function it_throws_validation_exception_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => $this->testEmail,
            'password' => Hash::make($this->testPassword),
        ]);

        $request = new LoginRequest();
        $request->merge([
            'email' => $this->testEmail,
            'password' => 'wrong-password',
        ]);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->validateResolved();

        try {
            $request->authenticateUser();
            $this->fail('Expected ValidationException was not thrown');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertEquals(__('auth.failed'), $e->errors()['email'][0]);
        }
    }

    #[Test]
    public function it_prevents_too_many_login_attempts()
    {
        // Create a user
        User::factory()->create([
            'email' => $this->testEmail,
            'password' => Hash::make($this->testPassword),
        ]);

        // Simulate too many login attempts
        for ($i = 0; $i < 6; $i++) {
            $request = new LoginRequest();
            $request->merge([
                'email' => $this->testEmail,
                'password' => 'wrong-password',
            ]);
            $request->server->set('REMOTE_ADDR', '127.0.0.1');
            $request->setContainer($this->app);
            $request->setRedirector($this->app['redirect']);
            $request->validateResolved();

            try {
                $request->authenticateUser();

                // If we get here and it's the 6th attempt, the test should fail
                if ($i >= 5) {
                    $this->fail('Expected rate limit exception was not thrown');
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                // For the first 5 attempts, we expect auth failed
                if ($i < 5) {
                    $this->assertEquals(__('auth.failed'), $e->errors()['email'][0]);
                    continue;
                }

                // On the 6th attempt, we should hit the rate limit
                $this->assertStringContainsString('Too many login attempts', $e->getMessage());
                return;
            }
        }

        $this->fail('Expected rate limit exception was not thrown after multiple attempts');
    }

    #[Test]
    public function it_generates_correct_throttle_key()
    {
        $request = new LoginRequest();
        $request->merge(['email' => $this->testEmail]);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $expectedKey = Str::lower($this->testEmail) . '|127.0.0.1';
        $this->assertSame($expectedKey, $request->throttleKey());
    }
}
