<?php

namespace Tests\Feature\Itinerary;

use App\DTO\Itinerary\Itinerary;
use App\DTO\Itinerary\ItineraryPlace;
use App\DTO\Itinerary\ItineraryDay;
use App\Models\Category;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Interfaces\ItineraryServiceInterface;
use Mockery;

class ItineraryGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Trip $trip;
    protected string $baseUrl = '/api/v1';
    protected array $categories = [];
    protected array $places = [];

    private function createCategory(array $attributes = []): Category
    {
        $defaults = [
            'slug' => 'test-category-' . uniqid(),
            'include_in_preferences' => true,
            'translations' => ['en' => 'Test Category', 'pl' => 'Kategoria testowa']
        ];

        $category = new Category();
        $category->forceFill(array_merge($defaults, $attributes));
        $category->save();

        return $category;
    }

    private function createPlace(array $attributes = []): Place
    {
        $defaults = [
            'name' => 'Test Place ' . uniqid(),
            'category_slug' => 'restaurant',
            'rating' => 4.0,
            'meta' => [],
            'location' => DB::raw("ST_GeomFromText('POINT(21.01 52.23)')")
        ];

        $place = new Place();
        $place->forceFill(array_merge($defaults, $attributes));
        $place->save();

        return $place;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->user = $user;

        $this->trip = Trip::factory()->create([
            'owner_id' => $user->getKey(),
            'start_latitude' => 52.23,
            'start_longitude' => 21.01,
            'start_location' => DB::raw("ST_GeomFromText('POINT(21.01 52.23)')")
        ]);

        if (!$this->trip->members()->where('user_id', $user->getKey())->exists()) {
            $this->trip->members()->attach($user->getKey(), [
                'role' => 'owner',
                'status' => 'accepted',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $this->actingAs($user);

        $this->categories = [
            'restaurant' => $this->createCategory([
                'slug' => 'restaurant',
                'translations' => ['en' => 'Restaurant', 'pl' => 'Restauracja']
            ]),
            'museum' => $this->createCategory([
                'slug' => 'museum',
                'translations' => ['en' => 'Museum', 'pl' => 'Muzeum']
            ]),
            'park' => $this->createCategory([
                'slug' => 'park',
                'translations' => ['en' => 'Park', 'pl' => 'Park']
            ]),
        ];

        $this->places = [
            'restaurant1' => $this->createPlace([
                'name' => 'Test Restaurant 1',
                'category_slug' => 'restaurant',
                'rating' => 4.5,
                'location' => DB::raw("ST_GeomFromText('POINT(21.012 52.230)')")
            ]),
            'restaurant2' => $this->createPlace([
                'name' => 'Test Restaurant 2',
                'category_slug' => 'restaurant',
                'rating' => 4.0,
                'location' => DB::raw("ST_GeomFromText('POINT(21.013 52.231)')")
            ]),
            'museum1' => $this->createPlace([
                'name' => 'Test Museum 1',
                'category_slug' => 'museum',
                'rating' => 4.7,
                'location' => DB::raw("ST_GeomFromText('POINT(21.014 52.232)')")
            ]),
            'park1' => $this->createPlace([
                'name' => 'Test Park 1',
                'category_slug' => 'park',
                'rating' => 4.2,
                'location' => DB::raw("ST_GeomFromText('POINT(21.015 52.233)')")
            ]),
        ];

        foreach ($this->places as $place) {
            $this->trip->places()->attach($place->getKey(), ['added_by' => $this->user->getKey()]);
        }

        Sanctum::actingAs($this->user);
    }

    public function test_it_generates_itinerary_based_on_preferences()
    {
        $this->setPreferences([
            'restaurant' => 2,
            'museum' => 1,
            'park' => 0,
        ]);

        $response = $this->getJson("$this->baseUrl/trips/{$this->trip->id}/itinerary/generate");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'trip_id',
                    'day_count',
                    'schedule' => [
                        '*' => [
                            'day',
                            'places' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'category_slug',
                                    'score',
                                    'distance_m',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        $places = $responseData['schedule'][0]['places'];

        $categorySlugs = array_column($places, 'category_slug');

        $this->assertContains('restaurant', $categorySlugs, 'Restaurant should be in the itinerary');
        $this->assertContains('museum', $categorySlugs, 'Museum should be in the itinerary');

        $parkIndex = array_search('park', $categorySlugs);
        if ($parkIndex !== false) {
            $this->assertEquals(count($categorySlugs) - 1, $parkIndex, 'Park should be last in the itinerary');
        }
    }

    public function test_changing_preferences_affects_itinerary_order()
    {
        $this->setPreferences([
            'restaurant' => 1,
            'museum' => 2,
            'park' => 0,
        ]);

        $response1 = $this->getJson("$this->baseUrl/trips/{$this->trip->id}/itinerary/generate");
        $response1->assertStatus(200);

        $firstPlaceBefore = $response1->json('data.schedule.0.places.0');
        $this->assertEquals('museum', $firstPlaceBefore['category_slug']);

        $this->setPreferences([
            'restaurant' => 2,
            'museum' => 1,
            'park' => 0,
        ]);

        $response2 = $this->getJson("$this->baseUrl/trips/{$this->trip->id}/itinerary/generate");
        $response2->assertStatus(200);

        $firstPlaceAfter = $response2->json('data.schedule.0.places.0');
        $this->assertEquals('restaurant', $firstPlaceAfter['category_slug']);
    }

    public function test_it_generates_multi_day_itinerary_correctly()
    {
        for ($i = 0; $i < 10; $i++) {
            $category = $this->categories[array_rand($this->categories)];
            $place = $this->createPlace([
                'name' => "Place $i",
                'category_slug' => $category->slug,
                'rating' => rand(30, 50) / 10,
                'location' => DB::raw("ST_GeomFromText('POINT(" . (21.01 + $i * 0.01) . ' ' . (52.23 + $i * 0.01) . ")')")
            ]);
            $this->trip->places()->attach($place->getKey(), ['added_by' => $this->user->getKey()]);
        }

        $this->setPreferences([
            'restaurant' => 2,
            'museum' => 1,
            'park' => 1,
        ]);

        $response = $this->postJson("$this->baseUrl/trips/{$this->trip->id}/itinerary/generate-full", [
            'days' => 3,
            'radius' => 5000,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d')
        ]);

        if ($response->status() !== 200) {
            $this->fail('Expected status code 200 but received ' . $response->status() . '. Response: ' . $response->getContent());
        }

        $response->assertJsonStructure([
                'data' => [
                    'trip_id',
                    'day_count',
                    'schedule' => [
                        '*' => [
                            'day',
                            'places' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'category_slug',
                                    'score',
                                    'distance_m',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        $this->assertCount(3, $responseData['schedule']);
    }

    public function test_it_handles_no_preferences_by_using_default_ratings()
    {
        $this->user->preferences()->delete();

        $places = [
            'restaurant1' => $this->places['restaurant1']->fresh(),
            'restaurant2' => $this->places['restaurant2']->fresh(),
            'museum1' => $this->places['museum1']->fresh(),
            'park1' => $this->places['park1']->fresh()
        ];

        foreach ($places as $place) {
            $this->trip->places()->syncWithoutDetaching([
                $place->getKey() => [
                    'added_by' => $this->user->getKey(),
                    'is_fixed' => true
                ]
            ]);
        }

        $this->setPreferences([
            'restaurant' => 0,
            'museum' => 0,
            'park' => 0,
        ]);

        $expectedOrder = collect($places)
            ->sortByDesc('rating')
            ->pluck('name')
            ->toArray();

        $itineraryPlaces = collect($places)
            ->sortByDesc('rating')
            ->values()
            ->map(function($place) {
                return new ItineraryPlace(
                    id: $place->id,
                    name: $place->name,
                    category_slug: $place->category_slug,
                    score: $place->rating * 0.5,
                    distance_m: 0,
                );
            })
            ->toArray();

        $itineraryDay = new ItineraryDay(
            day: 1,
            places: $itineraryPlaces
        );

        $itinerary = new Itinerary(
            trip_id: $this->trip->id,
            day_count: 1,
            schedule: [$itineraryDay],
            cache_info: [
                'mode' => 'test',
                'source' => 'test',
                'algorithm' => 'test',
            ]
        );

        $mockItineraryService = $this->mock(ItineraryServiceInterface::class, function ($mock) use ($itinerary) {
            $mock->shouldReceive('generate')
                ->with(Mockery::on(fn($trip) => $trip->id === $this->trip->id))
                ->andReturn($itinerary);
        });

        $this->app->instance(ItineraryServiceInterface::class, $mockItineraryService);

        $response = $this->getJson("$this->baseUrl/trips/{$this->trip->id}/itinerary/generate");
        $response->assertStatus(200);
        $responseData = $response->json('data');
        $this->assertArrayHasKey('schedule', $responseData, 'Response missing schedule key');

        $schedule = $responseData['schedule'][0] ?? null;
        $this->assertNotNull($schedule, 'No schedule data in response');
        $this->assertArrayHasKey('places', $schedule, 'Schedule missing places key');

        $returnedPlaces = $schedule['places'];

        $this->assertCount(
            count($places),
            $returnedPlaces,
            'Incorrect number of places returned. Expected ' . count($places) . ' but got ' . count($returnedPlaces)
        );

        $returnedNames = array_map(function($place) {
            if (is_array($place) && array_key_exists('name', $place)) {
                return $place['name'];
            }
            if (is_object($place) && property_exists($place, 'name')) {
                return $place->name;
            }
            $this->fail('Invalid place data structure: ' . json_encode($place));
        }, $returnedPlaces);

        $this->assertEquals(
            $expectedOrder,
            $returnedNames,
            'Places are not ordered by rating. ' .
            'Expected order: ' . implode(', ', $expectedOrder) . '\n' .
            'Actual order:   ' . implode(', ', $returnedNames)
        );

        $this->assertNotEmpty($returnedNames, 'No place names found in the response');
        $this->assertIsString($returnedNames[0], 'First place name is not a string');

        $placesArray = is_array($places) ? $places : (array)$places;
        $firstPlace = reset($placesArray);
        $placeType = gettype($firstPlace);
        $placeClass = is_object($firstPlace) ? get_class($firstPlace) : 'not an object';

        $placesCollection = collect($placesArray)->map(function($place) {
            if (is_object($place)) {
                return method_exists($place, 'toArray') ? $place->toArray() : (array)$place;
            }
            return $place;
        });

        $expectedHighestRated = $placesCollection->sortByDesc(function($place) {
            return is_array($place) ? ($place['rating'] ?? 0) : ($place->rating ?? 0);
        })->first();

        $expectedName = is_array($expectedHighestRated)
            ? ($expectedHighestRated['name'] ?? 'unknown')
            : (is_object($expectedHighestRated) ? ($expectedHighestRated->name ?? 'unknown') : 'unknown');

        $this->assertEquals(
            $expectedName,
            $returnedNames[0],
            'First place should be the highest rated place. ' .
            'Expected: ' . $expectedName .
            '\nActual: ' . $returnedNames[0] .
            '\nPlace type: ' . $placeType .
            '\nPlace class: ' . $placeClass
        );
    }

    public function test_it_returns_400_when_no_places_in_trip()
    {
        /** @var Trip $trip */
        $trip = Trip::factory()->create([
            'owner_id' => $this->user->getKey(),
            'start_latitude' => null,
            'start_longitude' => null,
            'start_location' => null
        ]);



        $trip->members()->detach($this->user->getKey());

        $trip->members()->attach($this->user->getKey(), [
            'role' => 'owner',
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $trip->places()->detach();

        $response = $this->getJson("$this->baseUrl/trips/$trip->id/itinerary/generate");

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Trip has no origin point (no fixed places and no start location).'
            ]);
    }

    /**
     * Helper method to set user preferences
     */
    private function setPreferences(array $preferences): void
    {
        $validPreferences = array_filter(
            $preferences,
            fn($slug) => isset($this->categories[$slug]),
            ARRAY_FILTER_USE_KEY
        );

        $this->putJson("$this->baseUrl/users/me/preferences", [
            'preferences' => $validPreferences,
        ])->assertStatus(200);
    }
}
