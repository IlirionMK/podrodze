<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UpdateTripRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test route to test the request
        Route::put('/test-route/{trip}', function (UpdateTripRequest $request) {
            return response()->json($request->validated());
        })->middleware('web');
    }

    #[Test]
    public function it_accepts_empty_request()
    {
        $trip = Trip::factory()->create();

        $response = $this->putJson("/test-route/$trip->id", []);

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    #[Test]
    public function it_validates_name_length()
    {
        $trip = Trip::factory()->create();

        // Test min length (2)
        $response = $this->putJson("/test-route/$trip->id", [
            'name' => 'a',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');

        // Test max length (255)
        $response = $this->putJson("/test-route/$trip->id", [
            'name' => str_repeat('a', 256),
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');

        // Test valid name
        $name = 'Updated Trip Name';
        $response = $this->putJson("/test-route/$trip->id", [
            'name' => $name,
        ]);
        $response->assertStatus(200);
        $this->assertEquals($name, $response->json('name'));
    }

    #[Test]
    public function it_validates_start_date_format()
    {
        $trip = Trip::factory()->create();

        $response = $this->putJson("/test-route/$trip->id", [
            'start_date' => 'invalid-date',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');

        // Test valid date
        $date = '2025-12-25';
        $response = $this->putJson("/test-route/$trip->id", [
            'start_date' => $date,
        ]);
        $response->assertStatus(200);
        $this->assertEquals($date, $response->json('start_date'));

        // Test null value
        $response = $this->putJson("/test-route/$trip->id", [
            'start_date' => null,
        ]);
        $response->assertStatus(200);
        $this->assertNull($response->json('start_date'));
    }

    #[Test]
    public function it_validates_end_date_format()
    {
        $trip = Trip::factory()->create();

        $response = $this->putJson("/test-route/$trip->id", [
            'end_date' => 'invalid-date',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');

        // Test valid date
        $date = '2025-12-31';
        $response = $this->putJson("/test-route/$trip->id", [
            'end_date' => $date,
        ]);
        $response->assertStatus(200);
        $this->assertEquals($date, $response->json('end_date'));

        // Test null value
        $response = $this->putJson("/test-route/$trip->id", [
            'end_date' => null,
        ]);
        $response->assertStatus(200);
        $this->assertNull($response->json('end_date'));
    }

    #[Test]
    public function it_validates_end_date_after_start_date()
    {
        $trip = Trip::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-10',
        ]);

        // Test end date before start date
        $response = $this->putJson("/test-route/$trip->id", [
            'start_date' => '2025-01-15',
            'end_date' => '2025-01-10',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');

        // Test valid date range
        $response = $this->putJson("/test-route/$trip->id", [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-15',
        ]);
        $response->assertStatus(200);

        // Test same date is allowed
        $response = $this->putJson("/test-route/$trip->id", [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-01',
        ]);
        $response->assertStatus(200);
    }

    #[Test]
    public function it_validates_description_length()
    {
        $trip = Trip::factory()->create();

        // Test max length (500)
        $response = $this->putJson("/test-route/$trip->id", [
            'description' => str_repeat('a', 501),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('description');

        // Test valid description
        $description = 'Updated trip description';
        $response = $this->putJson("/test-route/$trip->id", [
            'description' => $description,
        ]);
        $response->assertStatus(200);
        $this->assertEquals($description, $response->json('description'));

        // Test null value
        $response = $this->putJson("/test-route/$trip->id", [
            'description' => null,
        ]);
        $response->assertStatus(200);
        $this->assertNull($response->json('description'));
    }

    #[Test]
    public function it_handles_partial_updates()
    {
        $trip = Trip::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        // Update only name
        $response = $this->putJson("/test-route/$trip->id", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'Updated Name',
        ]);
        $this->assertArrayNotHasKey('description', $response->json());

        // Then update only description
        $response = $this->putJson("/test-route/$trip->id", [
            'description' => 'Updated Description',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'description' => 'Updated Description',
        ]);
        $this->assertArrayNotHasKey('name', $response->json());
    }
}
