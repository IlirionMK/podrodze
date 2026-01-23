<?php

namespace Http\Controllers\Auth;

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VerifyEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    private VerifyEmailController $controller;
    private string $frontendUrl = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new VerifyEmailController();
        config(['app.frontend_url' => $this->frontendUrl]);
        Event::fake();
    }

    public function test_email_verification_success()
    {
        $user = User::factory()->unverified()->create([
            'email_verified_at' => null,
        ]);

        $hash = sha1($user->getEmailForVerification());
        $request = Request::create("/verify-email/$user->id/$hash", 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $this->controller->__invoke($request, $user->id, $hash);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([
            'message' => 'Email verified successfully.',
            'code' => 'verified',
        ], $response->getData(true));

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        Event::assertDispatched(Verified::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_email_verification_invalid_hash()
    {
        $user = User::factory()->unverified()->create([
            'email_verified_at' => null,
        ]);

        $invalidHash = 'invalid-hash';
        $request = Request::create("/verify-email/$user->id/$invalidHash", 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $this->controller->__invoke($request, $user->id, $invalidHash);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals([
            'message' => 'Invalid verification link.',
            'code' => 'invalid_verification_link',
        ], $response->getData(true));

        $user->refresh();
        $this->assertNull($user->email_verified_at);

        Event::assertNotDispatched(Verified::class);
    }

    public function test_email_already_verified()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $hash = sha1($user->getEmailForVerification());
        $request = Request::create("/verify-email/$user->id/$hash", 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $this->controller->__invoke($request, $user->id, $hash);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([
            'message' => 'Email already verified.',
            'code' => 'already_verified',
        ], $response->getData(true));

        Event::assertNotDispatched(Verified::class);
    }

    public function test_redirects_to_frontend_for_web_requests()
    {
        $user = User::factory()->unverified()->create([
            'email_verified_at' => null,
        ]);

        $hash = sha1($user->getEmailForVerification());

        // Simulate a web request (no Accept: application/json header)
        $request = Request::create("/verify-email/$user->id/$hash", 'GET');
        $request->headers->set('Accept', 'text/html');

        $response = $this->controller->__invoke($request, $user->id, $hash);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertEquals("$this->frontendUrl/auth/verify-email?status=verified", $response->getTargetUrl());

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        Event::assertDispatched(Verified::class);
    }
}
