<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\TripPlaceUpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TripPlaceUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test route to test the request
        Route::put('/test-route', function (TripPlaceUpdateRequest $request) {
            return response()->json($request->validated());
        })->middleware('api');
    }

    #[Test]
    public function it_accepts_empty_request()
    {
        $response = $this->putJson('/test-route', []);

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    #[Test]
    public function it_validates_status_field()
    {
        $response = $this->putJson('/test-route', [
            'status' => 'invalid-status',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    #[Test]
    public function it_accepts_valid_status_values()
    {
        $validStatuses = ['proposed', 'selected', 'rejected', 'planned'];

        foreach ($validStatuses as $status) {
            $response = $this->putJson('/test-route', [
                'status' => $status,
            ]);

            $response->assertStatus(200);
            $this->assertEquals($status, $response->json('status'));
        }
    }

    #[Test]
    public function it_validates_is_fixed_as_boolean()
    {
        // Test non-boolean
        $response = $this->putJson('/test-route', [
            'is_fixed' => 'not-a-boolean',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_fixed');
        
        // Test valid boolean values
        $response = $this->putJson('/test-route', [
            'is_fixed' => true,
        ]);
        $response->assertStatus(200);
        $this->assertEquals(true, $response->json('is_fixed'));
        
        $response = $this->putJson('/test-route', [
            'is_fixed' => false,
        ]);
        $response->assertStatus(200);
        $this->assertEquals(false, $response->json('is_fixed'));
            }

    #[Test]
    public function it_validates_day_field()
    {
        // Test non-integer
        $response = $this->putJson('/test-route', [
            'day' => 'not-an-integer',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('day');
        
        // Test less than 1
        $response = $this->putJson('/test-route', [
            'day' => 0,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('day');
        
        // Test valid day
        $response = $this->putJson('/test-route', [
            'day' => 1,
        ]);
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('day'));
    }

    #[Test]
    public function it_validates_order_index_field()
    {
        // Test non-integer
        $response = $this->putJson('/test-route', [
            'order_index' => 'not-an-integer',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('order_index');
        $response->assertJsonValidationErrors('order_index');

        // Test less than 0
        $response = $this->putJson('/test-route', [
            'order_index' => -1,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('order_index');

        // Test valid order_index
        $response = $this->putJson('/test-route', [
            'order_index' => 0,
        ]);
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('order_index'));
    }

    #[Test]
    public function it_validates_note_field()
    {
        // Test max length
        $response = $this->putJson('/test-route', [
            'note' => str_repeat('a', 256),
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('note');
        $response->assertJsonValidationErrors('note');

        // Test valid note
        $note = 'This is a test note';
        $response = $this->putJson('/test-route', [
            'note' => $note,
        ]);
        $response->assertStatus(200);
        $this->assertEquals($note, $response->json('note'));
    }

    #[Test]
    public function it_accepts_multiple_fields_together()
    {
        $data = [
            'status' => 'selected',
            'is_fixed' => true,
            'day' => 2,
            'order_index' => 1,
            'note' => 'Updated note',
        ];

        $response = $this->putJson('/test-route', $data);

        $response->assertStatus(200);
        $response->assertJson($data);
    }
}
