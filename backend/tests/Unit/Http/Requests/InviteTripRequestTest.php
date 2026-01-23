<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\InviteTripRequest;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InviteTripRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test route to test the request
        Route::post('/test-route/{trip}', function (InviteTripRequest $request) {
            return response()->json($request->validated());
        })->middleware('web');
    }

    #[Test]
    public function it_authorizes_trip_owner()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson("/test-route/$trip->id", [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422); // Fails validation but passes authorization
    }

    #[Test]
    public function it_denies_non_owner()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(); // Different user owns this trip

        $this->actingAs($user);

        $response = $this->postJson("/test-route/$trip->id", [
            'email' => 'test@example.com',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function it_requires_valid_email()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson("/test-route/$trip->id", [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    #[Test]
    public function it_requires_existing_user_email()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson("/test-route/$trip->id", [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    #[Test]
    public function it_prevents_self_invite()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson("/test-route/$trip->id", [
            'email' => $user->email,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    #[Test]
    public function it_validates_role_field()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $otherUser = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson("/test-route/$trip->id", [
            'email' => $otherUser->email,
            'role' => 'invalid-role',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role');
    }

    #[Test]
    public function it_validates_message_length()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $otherUser = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson("/test-route/$trip->id", [
            'email' => $otherUser->email,
            'message' => str_repeat('a', 256),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('message');
    }

    #[Test]
    public function it_accepts_valid_data()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $otherUser = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson("/test-route/$trip->id", [
            'email' => $otherUser->email,
            'role' => 'editor',
            'message' => 'Please join my trip!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'email' => $otherUser->email,
            'role' => 'editor',
            'message' => 'Please join my trip!',
        ]);
    }
}
