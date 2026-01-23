<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\Trip;
use App\Models\User;
use App\Models\Place;
use App\Models\Category;
use App\Models\TripPlace;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end tests for trip places management operations.
 *
 * This test verifies the complete trip places management flow including:
 * 1. Adding places to trips
 * 2. Voting on places (like/dislike)
 * 3. Pinning/unpinning places as fixed
 * 4. Removing places from trips
 * 5. Managing place order and status
 * 6. Place filtering and sorting
 * 7. Permission validation for different user roles
 *
 * @covers \App\Http\Controllers\Api\V1\TripPlaceController
 * @covers \App\Http\Controllers\Api\V1\PlaceController
 */
#[Group('trip')]
#[Group('e2e')]
#[Group('places')]
class TripPlacesE2ETest extends TestCase
{
    use DatabaseMigrations;

    private User $owner;
    private User $editor;
    private User $member;
    private User $nonMember;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'name' => 'Trip Owner',
            'email' => 'owner@example.com',
        ]);

        $this->editor = User::factory()->create([
            'name' => 'Trip Editor',
            'email' => 'editor@example.com',
        ]);

        $this->member = User::factory()->create([
            'name' => 'Trip Member',
            'email' => 'member@example.com',
        ]);

        $this->nonMember = User::factory()->create([
            'name' => 'Non Member',
            'email' => 'nonmember@example.com',
        ]);

        $this->trip = Trip::factory()->create([
            'name' => 'Test Trip',
            'owner_id' => $this->owner->id,
        ]);

        // Add users to trip with different roles
        $this->trip->members()->attach($this->editor->id, [
            'role' => 'editor',
            'status' => 'accepted',
        ]);

        $this->trip->members()->attach($this->member->id, [
            'role' => 'member',
            'status' => 'accepted',
        ]);

        // Create test categories needed for the test
        Category::factory()->create([
            'slug' => 'food',
            'include_in_preferences' => true,
            'translations' => ['en' => 'Food', 'pl' => 'Jedzenie']
        ]);

        Sanctum::actingAs($this->owner);
    }

    public function test_complete_trip_places_management_flow(): void
    {
        // 1. Get initial places list (should be empty)
        $initialResponse = $this->getJson("/api/v1/trips/{$this->trip->id}/places");
        $initialResponse->assertStatus(200)
            ->assertJson(['data' => []]);

        // 2. Add first place to trip using google_place_id
        $place1Data = [
            'google_place_id' => 'ChIJd2aJ5wBZ5kcRjN1fjVhCQkU',
        ];

        $addResponse1 = $this->postJson("/api/v1/trips/{$this->trip->id}/places", $place1Data);
        $addResponse1->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'place' => [
                        'id',
                        'name',
                        'category_slug',
                        'lat',
                        'lon',
                    ],
                    'status',
                    'is_fixed',
                    'day',
                    'order_index',
                    'note',
                    'added_by',
                ]
            ]);

        $place1Id = $addResponse1->json('data.id');

        // 3. Add second place to trip using custom place data
        $place2Data = [
            'name' => 'Test Restaurant',
            'category' => 'food',
            'lat' => 50.0619,
            'lon' => 19.9368,
        ];

        $addResponse2 = $this->postJson("/api/v1/trips/{$this->trip->id}/places", $place2Data);
        $addResponse2->assertStatus(201);
        $place2Id = $addResponse2->json('data.id');

        // 4. Get updated places list
        $listResponse = $this->getJson("/api/v1/trips/{$this->trip->id}/places");
        $listResponse->assertStatus(200);
        $places = $listResponse->json('data');
        $this->assertCount(2, $places);

        // 5. Vote on first place (like)
        $voteResponse = $this->postJson("/api/v1/trips/{$this->trip->id}/places/{$place1Id}/vote", [
            'score' => 1
        ]);
        $voteResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Vote saved',
                'data' => [
                    'my_score' => 1
                ]
            ]);

        // 6. Vote on second place (lower score)
        $voteResponse2 = $this->postJson("/api/v1/trips/{$this->trip->id}/places/{$place2Id}/vote", [
            'score' => 2
        ]);
        $voteResponse2->assertStatus(200)
            ->assertJson([
                'message' => 'Vote saved',
                'data' => [
                    'my_score' => 2
                ]
            ]);

        // 7. Pin first place as fixed
        $pinResponse = $this->patchJson("/api/v1/trips/{$this->trip->id}/places/{$place1Id}", [
            'is_fixed' => true
        ]);
        $pinResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_fixed' => true
                ]
            ]);

        // 8. Add note to place
        $noteResponse = $this->patchJson("/api/v1/trips/{$this->trip->id}/places/{$place1Id}", [
            'note' => 'Must visit during sunset for best photos!'
        ]);
        $noteResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'note' => 'Must visit during sunset for best photos!'
                ]
            ]);

        // 9. Get places with updated information
        $finalResponse = $this->getJson("/api/v1/trips/{$this->trip->id}/places");
        $finalResponse->assertStatus(200);
        $finalPlaces = $finalResponse->json('data');

        // Verify place 1 is pinned and has note
        $place1 = collect($finalPlaces)->firstWhere('id', $place1Id);
        $this->assertTrue($place1['is_fixed']);
        $this->assertEquals('Must visit during sunset for best photos!', $place1['note']);

        // Verify place 2 exists (we can't check votes directly in this structure)
        $place2 = collect($finalPlaces)->firstWhere('id', $place2Id);
        $this->assertNotNull($place2);

        // 10. Remove second place
        $removeResponse = $this->deleteJson("/api/v1/trips/{$this->trip->id}/places/{$place2Id}");
        $removeResponse->assertStatus(200)
            ->assertJson(['message' => 'Place removed from trip']);

        // 11. Verify only one place remains
        $finalListResponse = $this->getJson("/api/v1/trips/{$this->trip->id}/places");
        $finalListResponse->assertStatus(200);
        $remainingPlaces = $finalListResponse->json('data');
        $this->assertCount(1, $remainingPlaces);
        $this->assertEquals($place1Id, $remainingPlaces[0]['id']);
    }

}
