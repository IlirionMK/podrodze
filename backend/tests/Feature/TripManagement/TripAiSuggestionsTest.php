<?php

declare(strict_types=1);

namespace Tests\Feature\TripManagement;

use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\TripTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for AI-powered trip suggestions.
 *
 * This test suite verifies the AI-powered trip suggestion functionality, including:
 * 1. Generation of personalized trip suggestions based on user preferences and trip context
 * 2. Integration with external AI/ML services for suggestion generation
 * 3. Processing and formatting of AI responses
 * 4. User interaction with suggestions (acceptance/rejection)
 * 5. Suggestion relevance and quality metrics
 *
 * @covers \App\Http\Controllers\Trip\AISuggestionController
 * @covers \App\Services\AISuggestionService
 * @covers \App\Models\AISuggestion
 */
#[Group('trip')]
#[Group('ai')]
#[Group('suggestions')]
#[Group('feature')]
class TripAiSuggestionsTest extends TripTestCase
{
    /** @var \App\Models\User The authenticated test user */
    protected User $user;

    /** @var \App\Models\Trip The test trip instance */
    protected \App\Models\Trip $trip;

    /**
     * Set up the test environment.
     * Creates a test user, authenticates them, and sets up a test trip.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($this->user);

        $this->trip = \App\Models\Trip::factory()->create([
            'name' => 'Test Trip',
            'description' => 'Test Description',
            'owner_id' => $this->user->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);

        config(['ai.suggestions.enabled' => true]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => [
                        'content' => json_encode([
                            'suggestions' => [
                                [
                                    'place_id' => 'test_place_1',
                                    'name' => 'AI Suggested Place',
                                    'reasons' => ['Popular with similar travelers', 'Matches your interests']
                                ]
                            ]
                        ])
                    ]]
                ]
            ]),
            'maps.googleapis.com/*' => Http::response([
                'results' => [
                    [
                        'place_id' => 'test_place_1',
                        'name' => 'Test Place 1',
                        'vicinity' => 'Test Address 1',
                        'rating' => 4.5,
                        'user_ratings_total' => 100,
                        'types' => ['tourist_attraction']
                    ]
                ]
            ])
        ]);
    }

    #[Test]
    public function it_displays_recommended_places()
    {
        $this->trip->update([
            'start_latitude' => 52.23,
            'start_longitude' => 21.01
        ]);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'address',
                        'rating',
                        'total_ratings',
                        'types',
                        'ai_reason'
                    ]
                ]
            ]);
    }

    #[Test]
    public function user_can_accept_recommended_place()
    {
        $place = Place::factory()->create([
            'name' => 'Test Place',
            'google_place_id' => 'test_place_1',
            'category_slug' => 'attraction',
            'rating' => 4.5,
            'meta' => [
                'formatted_address' => 'Test Address 1',
                'geometry' => ['location' => ['lat' => 52.23, 'lng' => 21.01]],
                'types' => ['tourist_attraction'],
                'user_ratings_total' => 100
            ]
        ]);

        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/places", [
            'place_id' => $place->id,
            'status' => 'selected',
            'day' => 1,
            'order_index' => 1
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('trip_place', [
            'trip_id' => $this->trip->id,
            'place_id' => $place->id,
            'status' => 'selected'
        ]);
    }

    #[Test]
    public function user_can_reject_recommended_place()
    {
        $place = Place::factory()->create([
            'name' => 'Test Place 2',
            'google_place_id' => 'test_place_2',
            'category_slug' => 'museum',
            'rating' => 4.7,
            'meta' => [
                'formatted_address' => 'Test Address 2',
                'geometry' => ['location' => ['lat' => 52.24, 'lng' => 21.02]],
                'types' => ['museum'],
                'user_ratings_total' => 200
            ]
        ]);

        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/places", [
            'place_id' => $place->id,
            'status' => 'rejected'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('trip_place', [
            'trip_id' => $this->trip->id,
            'place_id' => $place->id,
            'status' => 'rejected'
        ]);
    }

    #[Test]
    public function trip_plan_updates_after_accepting_recommendation()
    {
        $place = Place::factory()->create([
            'name' => 'Test Place 3',
            'google_place_id' => 'test_place_3',
            'category_slug' => 'restaurant',
            'rating' => 4.8,
            'meta' => [
                'formatted_address' => 'Test Address 3',
                'geometry' => ['location' => ['lat' => 52.25, 'lng' => 21.03]],
                'types' => ['restaurant'],
                'user_ratings_total' => 150
            ]
        ]);

        $this->postJson("/api/v1/trips/{$this->trip->id}/places", [
            'place_id' => $place->id,
            'status' => 'selected',
            'day' => 1,
            'order_index' => 1
        ]);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $place->id,
                'name' => $place->name
            ]);
    }
}
