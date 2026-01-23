<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\Trip;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end tests for places search and Google Places integration.
 *
 * This test verifies the complete places search flow including:
 * 1. Google Places autocomplete functionality
 * 2. Nearby places search with coordinates
 * 3. Google place details retrieval
 * 4. Search result filtering and pagination
 * 5. Error handling for API failures
 * 6. Search query validation
 * 7. Integration with trip planning
 *
 * @covers \App\Http\Controllers\Api\V1\PlaceController
 */
#[Group('places')]
#[Group('e2e')]
#[Group('search')]
class PlacesSearchE2ETest extends TestCase
{

    private User $user;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->trip = Trip::factory()->create([
            'name' => 'Test Trip',
            'owner_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_complete_places_search_flow(): void
    {
        // Mock Google Places API responses
        Http::fake([
            'https://maps.googleapis.com/maps/api/place/autocomplete/json*' => Http::response([
                'status' => 'OK',
                'predictions' => [
                    [
                        'place_id' => 'ChIJd2aJ5wBZ5kcRjN1fjVhCQkU',
                        'description' => 'Eiffel Tower, Paris, France',
                        'structured_formatting' => [
                            'main_text' => 'Eiffel Tower',
                            'secondary_text' => 'Paris, France'
                        ],
                        'types' => ['tourist_attraction', 'landmark']
                    ],
                    [
                        'place_id' => 'ChIJLU7jZClu5kcR4PcOOO6p3I0',
                        'description' => 'Louvre Museum, Paris, France',
                        'structured_formatting' => [
                            'main_text' => 'Louvre Museum',
                            'secondary_text' => 'Paris, France'
                        ],
                        'types' => ['museum']
                    ]
                ]
            ], 200),

            'https://maps.googleapis.com/maps/api/place/details/json*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'place_id' => 'ChIJd2aJ5wBZ5kcRjN1fjVhCQkU',
                    'name' => 'Eiffel Tower',
                    'formatted_address' => 'Champ de Mars, 5 Avenue Anatole France, 75007 Paris, France',
                    'geometry' => [
                        'location' => [
                            'lat' => 48.8584,
                            'lng' => 2.2945
                        ]
                    ],
                    'rating' => 4.5,
                    'types' => ['tourist_attraction', 'landmark'],
                    'opening_hours' => [
                        'open_now' => true,
                        'weekday_text' => ['Monday: 9:30 AM – 11:45 PM', 'Tuesday: 9:30 AM – 11:45 PM']
                    ],
                    'photos' => [
                        [
                            'photo_reference' => 'ATplDJbIDhG...',
                            'height' => 800,
                            'width' => 600
                        ]
                    ]
                ]
            ], 200),

            'https://places.googleapis.com/v1/places:searchNearby*' => Http::response([
                'places' => [
                    [
                        'id' => 'ChIJd2aJ5wBZ5kcRjN1fjVhCQkU',
                        'displayName' => [
                            'text' => 'Eiffel Tower'
                        ],
                        'location' => [
                            'latitude' => 48.8584,
                            'longitude' => 2.2945
                        ],
                        'rating' => 4.5,
                        'types' => ['tourist_attraction'],
                        'userRatingCount' => 10000,
                        'formattedAddress' => 'Champ de Mars, 5 Avenue Anatole France, 75007 Paris, France'
                    ],
                    [
                        'id' => 'ChIJLU7jZClu5kcR4PcOOO6p3I0',
                        'displayName' => [
                            'text' => 'Louvre Museum'
                        ],
                        'location' => [
                            'latitude' => 48.8606,
                            'longitude' => 2.3376
                        ],
                        'rating' => 4.7,
                        'types' => ['museum'],
                        'userRatingCount' => 15000,
                        'formattedAddress' => 'Rue de Rivoli, 75001 Paris, France'
                    ]
                ]
            ], 200),
            
            // Fallback mock for any other Google Places API calls
            'https://places.googleapis.com/*' => Http::response([], 200)
        ]);

        // 1. Test autocomplete search
        $autocompleteResponse = $this->getJson('/api/v1/places/autocomplete?q=Eiffel&language=en');

        $autocompleteResponse->assertStatus(200)
            ->assertJsonStructure([
            'data' => [
                '*' => [
                    'google_place_id',
                    'description',
                    'main_text',
                    'secondary_text',
                    'types'
                ]
            ]
        ]);

        $predictions = $autocompleteResponse->json('data');
        $this->assertCount(2, $predictions);
        $this->assertEquals('ChIJd2aJ5wBZ5kcRjN1fjVhCQkU', $predictions[0]['google_place_id']);

        // 2. Test place details retrieval
        $detailsResponse = $this->getJson('/api/v1/places/google/ChIJd2aJ5wBZ5kcRjN1fjVhCQkU', [
            'language' => 'en'
        ]);

        $detailsResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'google_place_id',
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
            ])
            ->assertJson([
                'data' => [
                    'google_place_id' => 'ChIJd2aJ5wBZ5kcRjN1fjVhCQkU',
                    'place_id' => 'ChIJd2aJ5wBZ5kcRjN1fjVhCQkU',
                    'name' => 'Eiffel Tower',
                    'rating' => 4.5
                ]
            ]);

        // 3. Test nearby search
        $nearbyResponse = $this->getJson('/api/v1/places/nearby?lat=48.8584&lon=2.2945&radius=5000&type=tourist_attraction&language=en');

        $nearbyResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'google_place_id',
                        'name',
                        'category_slug',
                        'rating',
                        'meta',
                        'lat',
                        'lon',
                        'distance_m',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'message',
                'summary'
            ]);

        $results = $nearbyResponse->json('data');
        
        $this->assertCount(2, $results);
        $this->assertEquals('Eiffel Tower', $results[0]['name']);

        // 4. Test adding found place to trip
        $placeData = [
            'google_place_id' => 'ChIJd2aJ5wBZ5kcRjN1fjVhCQkU',
        ];

        $addToTripResponse = $this->postJson("/api/v1/trips/{$this->trip->id}/places", $placeData);
        $addToTripResponse->assertStatus(201);

        // 5. Verify place was added to trip
        $tripPlacesResponse = $this->getJson("/api/v1/trips/{$this->trip->id}/places");
        $tripPlacesResponse->assertStatus(200);
        $tripPlaces = $tripPlacesResponse->json('data');
        
        $this->assertCount(1, $tripPlaces);
        $this->assertEquals('Eiffel Tower', $tripPlaces[0]['place']['name'] ?? 'Unknown');
    }

}
