<?php

namespace Tests\Feature\TripManagement;

use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\AuthenticatedTestCase;

class TripPlaceVoteTest extends AuthenticatedTestCase
{
    protected Trip $trip;
    protected Place $place;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAsUser($this->user);

        $this->trip = Trip::create([
            'name' => 'Test Trip',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'owner_id' => $this->user->id,
        ]);

        $this->place = Place::create([
            'name' => 'Test Place',
            'google_place_id' => 'test_place_123',
            'category_slug' => 'test-category',
            'rating' => 4.5,
            'meta' => ['address' => '123 Test St'],
            'opening_hours' => [
                'monday' => ['open' => '09:00', 'close' => '17:00'],
                'tuesday' => ['open' => '09:00', 'close' => '17:00'],
                'sunday' => null,
            ],
            'location' => DB::raw("ST_GeomFromText('POINT(0 0)')"),
        ]);

        $this->trip->places()->attach($this->place->id);
    }

    #[Test]
    public function user_can_vote_for_a_place_in_trip()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}/vote", [
                'score' => 3
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'place_id',
                    'my_score',
                    'avg_score',
                    'votes'
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertEquals($this->place->id, $responseData['place_id']);
        $this->assertEquals(3, $responseData['my_score']);
    }

    #[Test]
    public function user_can_update_their_vote()
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}/vote", [
                'score' => 3
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}/vote", [
                'score' => 5
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Vote saved',
                'data' => [
                    'my_score' => 5
                ]
            ]);
    }

    #[Test]
    public function user_cannot_vote_for_place_not_in_trip()
    {
        $otherPlace = Place::create([
            'name' => 'Another Place',
            'google_place_id' => 'another_place_456',
            'category_slug' => 'another-category',
            'rating' => 4.0,
            'meta' => ['address' => '456 Another St'],
            'opening_hours' => [
                'monday' => ['open' => '10:00', 'close' => '18:00']
            ],
            'location' => DB::raw("ST_GeomFromText('POINT(1 1)')"),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/$otherPlace->id/vote", [
                'score' => 3
            ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function vote_requires_authentication()
    {
        $this->refreshApplication();
        
        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}/vote", [
            'score' => 3
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function vote_requires_valid_score()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}/vote", [
                'score' => 0
            ]);
        $response->assertStatus(422);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}/vote", [
                'score' => 6
            ]);
        $response->assertStatus(422);
    }

    #[Test]
    public function can_list_votes_for_trip()
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}/vote", [
                'score' => 4
            ]);

        $place2 = Place::create([
            'name' => 'Second Place',
            'google_place_id' => 'second_place_789',
            'category_slug' => 'second-category',
            'rating' => 3.5,
            'meta' => ['address' => '789 Second St'],
            'opening_hours' => [
                'monday' => ['open' => '08:00', 'close' => '16:00']
            ],
            'location' => DB::raw("ST_GeomFromText('POINT(2 2)')"),
        ]);
        $this->trip->places()->attach($place2->id);

        $this->actingAs($this->user)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/{$place2->id}/vote", [
                'score' => 2
            ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/trips/{$this->trip->id}/places/votes");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'place_id',
                        'my_score',
                        'avg_score',
                        'votes'
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $place1Data = collect($data)->firstWhere('place_id', $this->place->id);
        $place2Data = collect($data)->firstWhere('place_id', $place2->id);

        $this->assertEquals(4, $place1Data['my_score']);
        $this->assertEquals(2, $place2Data['my_score']);
    }

    #[Test]
    public function list_votes_requires_authentication()
    {
        $this->refreshApplication();
        
        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/votes");
        $response->assertStatus(401);
    }

    #[Test]
    public function user_can_only_view_votes_for_their_trips()
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/trips/{$this->trip->id}/places/votes");

        $response->assertStatus(403);
    }

    #[Test]
    public function vote_requires_trip_membership()
    {
        $otherUser = User::create([
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/v1/trips/{$this->trip->id}/places/{$this->place->id}/vote", [
                'score' => 3
            ]);

        $response->assertStatus(403);
    }
}
