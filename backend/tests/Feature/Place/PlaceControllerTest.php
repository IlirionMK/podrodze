<?php

namespace Tests\Feature\Place;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PlaceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    #[Test]
    public function it_returns_nearby_places()
    {
        Http::fake([
            'https://maps.googleapis.com/maps/api/place/nearbysearch/*' => Http::response([
                'results' => [],
                'status' => 'ZERO_RESULTS'
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/nearby?lat=52.23&lon=21.01');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
                'message'
            ]);

        Http::fake([
            'https://maps.googleapis.com/maps/api/place/nearbysearch/*' => Http::response([
                'results' => [
                    [
                        'place_id' => 'test_place_1',
                        'name' => 'Test Place 1',
                        'vicinity' => 'Test Address 1',
                        'geometry' => [
                            'location' => [
                                'lat' => 52.23,
                                'lng' => 21.01
                            ]
                        ],
                        'types' => ['establishment']
                    ]
                ],
                'status' => 'OK'
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/nearby?lat=52.23&lon=21.01&radius=5000');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'address',
                        'lat',
                        'lng',
                        'google_place_id',
                        'types'
                    ]
                ],
                'message'
            ]);
    }

    #[Test]
    public function it_validates_nearby_places_parameters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/nearby');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lon']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/nearby?lat=100&lon=21.01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/nearby?lat=52.23&lon=200');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lon']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/nearby?lat=52.23&lon=21.01&radius=100000');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['radius']);
    }

    #[Test]
    public function it_returns_place_autocomplete_suggestions()
    {
        Http::fake([
            'https://maps.googleapis.com/maps/api/place/autocomplete/*' => Http::response([
                'predictions' => [
                    [
                        'description' => 'Test Place, Warsaw, Poland',
                        'place_id' => 'test_place_id',
                        'structured_formatting' => [
                            'main_text' => 'Test Place',
                            'secondary_text' => 'Warsaw, Poland'
                        ],
                        'types' => ['establishment', 'point_of_interest']
                    ]
                ],
                'status' => 'OK'
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/autocomplete?q=test');

        $response->assertStatus(200);
        
        $responseData = $response->json();
        
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);
        
        if (!empty($responseData['data'])) {
            $firstItem = $responseData['data'][0];
            
            $this->assertArrayHasKey('google_place_id', $firstItem);
            $this->assertArrayHasKey('description', $firstItem);
            $this->assertArrayHasKey('main_text', $firstItem);
            $this->assertArrayHasKey('secondary_text', $firstItem);
            $this->assertArrayHasKey('types', $firstItem);
            $this->assertIsArray($firstItem['types']);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/autocomplete?q=test&lat=52.23&lon=21.01&radius=5000');
        $response->assertStatus(200);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/autocomplete?q=test&language=en');
        $response->assertStatus(200);
    }

    #[Test]
    public function it_validates_autocomplete_parameters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/autocomplete');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/autocomplete?q=t');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_returns_google_place_details()
    {
        $place = Place::create([
            'google_place_id' => 'test_place_id',
            'name' => 'Test Place',
            'address' => 'Test Address',
            'lat' => 52.23,
            'lng' => 21.01,
            'category_slug' => 'test-category',
            'rating' => 4.5,
            'meta' => [
                'formatted_address' => 'Test Address',
                'types' => ['establishment'],
                'geometry' => [
                    'location' => [
                        'lat' => 52.23,
                        'lng' => 21.01,
                    ],
                ],
                'opening_hours' => [
                    'open_now' => true,
                    'weekday_text' => []
                ]
            ]
        ]);

        DB::statement("UPDATE places SET location = ST_GeomFromText('POINT(21.01 52.23)', 4326) WHERE id = ?", [$place->id]);

        $place->refresh();

        Http::fake([
            'https://maps.googleapis.com/maps/api/place/details/*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'place_id' => $place->google_place_id,
                    'name' => 'Test Place',
                    'formatted_address' => 'Test Address',
                    'geometry' => [
                        'location' => [
                            'lat' => 52.23,
                            'lng' => 21.01,
                        ]
                    ],
                    'types' => ['establishment'],
                    'opening_hours' => [
                        'open_now' => true,
                        'weekday_text' => []
                    ],
                    'rating' => 4.5,
                    'user_ratings_total' => 100,
                    'photos' => [],
                    'reviews' => [],
                    'vicinity' => 'Test Address',
                    'website' => 'https://example.com',
                    'international_phone_number' => '+48123456789',
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/places/google/$place->google_place_id");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'place_id',
                    'name',
                    'lat',
                    'lon',
                    'category_slug',
                    'types',
                    'rating',
                    'opening_hours',
                    'meta' => [
                        'address',
                        'user_ratings_total',
                        'website',
                        'phone',
                        'types'
                    ]
                ]
            ]);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_google_place()
    {
        Http::fake([
            'https://maps.googleapis.com/maps/api/place/details/*' => Http::response([
                'status' => 'NOT_FOUND'
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/places/google/nonexistent_place_id');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_requires_authentication()
    {
        $this->refreshApplication();

        $endpoints = [
            ['method' => 'GET', 'url' => '/api/v1/places/autocomplete?q=test', 'status' => 401],
            ['method' => 'GET', 'url' => '/api/v1/places/google/test_place_id', 'status' => 401],
            ['method' => 'GET', 'url' => '/api/v1/places/nearby?lat=52.23&lon=21.01', 'status' => 401],
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->json($endpoint['method'], $endpoint['url']);
            $response->assertStatus($endpoint['status']);
        }
    }
}
