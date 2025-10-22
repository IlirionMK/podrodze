<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user'  => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => \App\Models\User::class,
        ]);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(in_array($response->getStatusCode(), [401, 422]) ? $response->getStatusCode() : 401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/logout', [], [
            'Authorization' => "Bearer {$plain}",
        ]);

        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => \App\Models\User::class,
            'name'           => 'test-token',
        ]);
    }
}
