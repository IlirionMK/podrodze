<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\TripPlaceStoreRequest;
use App\Models\Category;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\HandlesCsrfTokens;
use PHPUnit\Framework\Attributes\Test;

class TripPlaceStoreRequestTest extends TestCase
{
    use RefreshDatabase, HandlesCsrfTokens;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test route to test the request
        Route::post('/test-route', function (TripPlaceStoreRequest $request) {
            return response()->json($request->validated());
        })->middleware('api');
    }

    #[Test]
    public function it_requires_at_least_one_source()
    {
        $response = $this->postJson('/test-route', $this->withCsrfToken());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['place_id']);
    }

    #[Test]
    public function it_validates_place_id_exists()
    {
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => 999,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['place_id']);
    }

    #[Test]
    public function it_accepts_valid_place_id()
    {
        $place = Place::factory()->create();

        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => $place->id,
        ]));

        $response->assertStatus(200);
    }

    #[Test]
    public function it_accepts_google_place_id()
    {
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'google_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
        ]));

        $response->assertStatus(200);
    }

    #[Test]
    public function it_requires_all_custom_fields_when_using_custom_place()
    {
        $category = Category::factory()->create();

        // Test missing name
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'category' => $category->slug,
            'lat' => 51.1079,
            'lon' => 17.0385,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
        
        // Test missing category
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'name' => 'Test Place',
            'lat' => 51.1079,
            'lon' => 17.0385,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
        
        // Test missing lat
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'name' => 'Test Place',
            'category' => $category->slug,
            'lon' => 17.0385,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);
        
        // Test missing lon
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'name' => 'Test Place',
            'category' => $category->slug,
            'lat' => 51.1079,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lon']);
    }

    #[Test]
    public function it_accepts_valid_custom_place()
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'name' => 'Test Place',
            'category' => $category->slug,
            'lat' => 51.1079,
            'lon' => 17.0385,
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Test Place',
                'category' => $category->slug,
                'lat' => 51.1079,
                'lon' => 17.0385,
            ]);
    }

    #[Test]
    public function it_validates_category_exists()
    {
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'name' => 'Test Place',
            'category' => 'nonexistent-category',
            'lat' => 51.1079,
            'lon' => 17.0385,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    #[Test]
    public function it_validates_lat_lon_as_numeric()
    {
        $category = Category::factory()->create();

        // Test invalid lat
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'name' => 'Test Place',
            'category' => $category->slug,
            'lat' => 'invalid',
            'lon' => 17.0385,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);
        
        // Test invalid lon
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'name' => 'Test Place',
            'category' => $category->slug,
            'lat' => 51.1079,
            'lon' => 'invalid',
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lon']);
    }

    #[Test]
    public function it_validates_optional_fields()
    {
        $place = Place::factory()->create();

        // Test valid optional fields
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => $place->id,
            'day' => 1,
            'order_index' => 0,
            'note' => 'Test note',
        ]));
        $response->assertStatus(200);

        // Test invalid status
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => $place->id,
            'status' => 'invalid-status',
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
            
        // Test invalid day
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => $place->id,
            'day' => 0,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['day']);

        // Test invalid order_index
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => $place->id,
            'order_index' => -1,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_index']);
        
        // Test note max length
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => $place->id,
            'note' => str_repeat('a', 1001),
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }

    #[Test]
    public function it_prevents_mixing_sources()
    {
        $place = Place::factory()->create();
        $category = Category::factory()->create();

        // Test place_id with google_place_id
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => $place->id,
            'google_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['place_id']);

        // Test place_id with custom fields
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'place_id' => $place->id,
            'name' => 'Test Place',
            'category' => $category->slug,
            'lat' => 51.1079,
            'lon' => 17.0385,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['place_id']);

        // Test google_place_id with custom fields
        $response = $this->postJson('/test-route', $this->withCsrfToken([
            'google_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'name' => 'Test Place',
            'category' => $category->slug,
            'lat' => 51.1079,
            'lon' => 17.0385,
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['place_id']);
    }
}
