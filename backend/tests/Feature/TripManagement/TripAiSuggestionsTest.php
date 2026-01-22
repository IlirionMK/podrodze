<?php

declare(strict_types=1);

namespace Tests\Feature\TripManagement;

use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\TripTestCase;

/**
 * Tests for AI-powered trip suggestions.
 *
 * This class verifies that:
 * - AI suggestions are generated based on trip context
 * - Suggestions are relevant and personalized
 * - Suggestion quality meets expectations
 * - User feedback on suggestions is processed correctly
 */
class TripAiSuggestionsTest extends TripTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($user);

        $this->trip = \App\Models\Trip::create([
            'name' => 'Test Trip',
            'description' => 'Test Description',
            'owner_id' => $user->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);

        config(['ai.suggestions.enabled' => true]);

        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'AI recommendation reason']]]]),
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
        $place = new Place([
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
        $place->save();

        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/places", [
            'place_id' => $place->id,
            'status' => 'selected',  // Changed from 'accepted' to 'selected' to match API validation
            'day' => 1,
            'order_index' => 1  // Changed from 'order' to 'order_index' to match the expected field name
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('trip_place', [
            'trip_id' => $this->trip->id,
            'place_id' => $place->id,
            'status' => 'selected'  // Changed from 'accepted' to 'selected'
        ]);
    }

    #[Test]
    public function user_can_reject_recommended_place()
    {
        $place = new Place([
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
        $place->save();

        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/places", [
            'place_id' => $place->id,
            'status' => 'rejected'  // 'rejected' is a valid status in the API
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
        $place = new Place([
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
        $place->save();

        $this->postJson("/api/v1/trips/{$this->trip->id}/places", [
            'place_id' => $place->id,
            'status' => 'selected',  // Changed from 'accepted' to 'selected' to match API validation
            'day' => 1,
            'order_index' => 1  // Changed from 'order' to 'order_index' to match the expected field name
        ]);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places");  // Changed endpoint to get trip places

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $place->id,
                'name' => $place->name
            ]);
    }
}
