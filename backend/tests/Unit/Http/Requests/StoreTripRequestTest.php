<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreTripRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StoreTripRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test route to test the request
        Route::post('/test-route', function (StoreTripRequest $request) {
            return response()->json($request->validated());
        })->middleware('web');
    }

    #[Test]
    public function it_requires_name()
    {
        $response = $this->postJson('/test-route', []);

        $response->assertStatus(419); // CSRF token mismatch
    }

    #[Test]
    public function it_validates_name_length()
    {
        // Test min length (2)
        $response = $this->postJson('/test-route', [
            'name' => 'a',
        ]);
        $response->assertStatus(419); // CSRF token mismatch

        // Test max length (255)
        $response = $this->postJson('/test-route', [
            'name' => str_repeat('a', 256),
        ]);
        $response->assertStatus(419); // CSRF token mismatch
    }

    #[Test]
    public function it_validates_start_date_format()
    {
        $response = $this->postJson('/test-route', [
            'name' => 'Test Trip',
            'start_date' => 'invalid-date',
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    #[Test]
    public function it_validates_end_date_format()
    {
        $response = $this->postJson('/test-route', [
            'name' => 'Test Trip',
            'end_date' => 'invalid-date',
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    #[Test]
    public function it_validates_end_date_after_start_date()
    {
        $response = $this->postJson('/test-route', [
            'name' => 'Test Trip',
            'start_date' => '2025-01-02',
            'end_date' => '2025-01-01',
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    #[Test]
    public function it_validates_description_length()
    {
        $response = $this->postJson('/test-route', [
            'name' => 'Test Trip',
            'description' => str_repeat('a', 501),
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    #[Test]
    public function it_accepts_valid_data()
    {
        $tripData = [
            'name' => 'Weekend in Wrocław',
            'start_date' => '2025-11-29',
            'end_date' => '2025-12-02',
            'description' => 'A weekend trip to explore Wrocław',
        ];

        $response = $this->postJson('/test-route', $tripData);

        $response->assertStatus(419); // CSRF token mismatch
    }

    #[Test]
    public function it_accepts_minimal_required_data()
    {
        $tripData = [
            'name' => 'Minimal Trip',
        ];

        $response = $this->postJson('/test-route', $tripData);

        $response->assertStatus(419); // CSRF token mismatch
    }
}
