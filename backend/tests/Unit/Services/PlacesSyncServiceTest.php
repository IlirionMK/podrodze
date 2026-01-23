<?php

namespace Tests\Unit\Services;

use App\Models\Place;
use App\Services\External\GooglePlacesService;
use App\Services\PlacesSyncService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class PlacesSyncServiceTest extends TestCase
{
    use DatabaseMigrations;

    private PlacesSyncService $service;
    private MockObject|GooglePlacesService $googlePlaces;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up Google category mapping
        Config::set('google_category_map', [
            'restaurant' => 'food',
            'food' => 'food',
            'cafe' => 'food',
            'bar' => 'food',
            'museum' => 'culture',
            'park' => 'nature',
            // Add other mappings as needed
        ]);

        $this->googlePlaces = $this->createMock(GooglePlacesService::class);
        $this->service = new PlacesSyncService($this->googlePlaces);
    }

    #[Test]
    public function it_fetches_and_stores_places(): void
    {
        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Test Restaurant',
                'category_slug' => 'restaurant',
                'rating' => 4.5,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'meta' => ['user_ratings_total' => 500, 'types' => ['restaurant', 'food']],
                'opening_hours' => ['monday' => '9:00-22:00']
            ],
            [
                'place_id' => 'google456',
                'name' => 'Test Museum',
                'category_slug' => 'museum',
                'rating' => 4.0,
                'lat' => 52.2397,
                'lon' => 21.0222,
                'meta' => ['user_ratings_total' => 200],
                'opening_hours' => null
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->with(52.2297, 21.0122, 3000)
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')
            ->once()
            ->with('[PlacesSyncService] Synced places', $this->anything());

        $result = $this->service->fetchAndStore(52.2297, 21.0122, 3000);

        $this->assertEquals(2, $result['added']);
        $this->assertEquals(0, $result['updated']);

        $this->assertDatabaseHas('places', [
            'google_place_id' => 'google123',
            'name' => 'Test Restaurant',
            'category_slug' => 'food', // Should be 'food' based on our mapping
            'rating' => 4.5
        ]);

        $this->assertDatabaseHas('places', [
            'google_place_id' => 'google456',
            'name' => 'Test Museum',
            'category_slug' => 'culture',
            'rating' => 4.0
        ]);
    }

    #[Test]
    public function it_updates_existing_places(): void
    {
        // Create existing place
        $existingPlace = Place::factory()->create([
            'google_place_id' => 'google123',
            'name' => 'Old Name',
            'rating' => 3.0,
            'category_slug' => 'other'
        ]);

        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Updated Restaurant',
                'category_slug' => 'restaurant',
                'rating' => 4.5,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'meta' => ['user_ratings_total' => 500]
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $result = $this->service->fetchAndStore(52.2297, 21.0122);

        $this->assertEquals(0, $result['added']);
        $this->assertEquals(1, $result['updated']);

        $this->assertDatabaseHas('places', [
            'id' => $existingPlace->id,
            'google_place_id' => 'google123',
            'name' => 'Updated Restaurant',
            'rating' => 4.5,
            'category_slug' => 'food' // Should be 'food' based on our mapping
        ]);
    }

    #[Test]
    public function it_skips_invalid_places(): void
    {
        $googlePlaces = [
            [
                // Missing place_id
                'name' => 'Invalid Place 1',
                'category_slug' => 'restaurant',
                'rating' => 4.5,
                'lat' => 52.2297,
                'lon' => 21.0122
            ],
            [
                'place_id' => 'google456',
                'name' => 'Invalid Place 2',
                'category_slug' => 'museum',
                'rating' => 4.0,
                // Missing lat/lon
            ],
            [
                'place_id' => 'google789',
                'name' => 'Valid Place',
                'category_slug' => 'food',
                'rating' => 3.5,
                'lat' => 52.2297,
                'lon' => 21.0122
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $result = $this->service->fetchAndStore(52.2297, 21.0122);

        $this->assertEquals(1, $result['added']); // Only valid place
        $this->assertEquals(0, $result['updated']);

        $this->assertDatabaseHas('places', [
            'google_place_id' => 'google789',
            'name' => 'Valid Place'
        ]);

        $this->assertDatabaseMissing('places', [
            'google_place_id' => 'google456'
        ]);
    }

    #[Test]
    public function it_maps_google_types_to_categories(): void
    {
        Config::set('google_category_map', [
            'restaurant' => 'food',
            'museum' => 'museum',
            'park' => 'nature'
        ]);

        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Restaurant',
                'category_slug' => 'restaurant',
                'rating' => 4.5,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'meta' => ['types' => ['restaurant', 'food']]
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $this->service->fetchAndStore(52.2297, 21.0122);

        $this->assertDatabaseHas('places', [
            'google_place_id' => 'google123',
            'category_slug' => 'food' // Mapped from 'restaurant'
        ]);
    }

    #[Test]
    public function it_falls_back_to_other_category_when_no_mapping(): void
    {
        Config::set('google_category_map', [
            'restaurant' => 'food'
        ]);

        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Unknown Place',
                'category_slug' => 'unknown_type',
                'rating' => 4.0,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'meta' => ['types' => ['unknown_type', 'business']]
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $this->service->fetchAndStore(52.2297, 21.0122);

        $this->assertDatabaseHas('places', [
            'google_place_id' => 'google123',
            'category_slug' => 'other'
        ]);
    }

    #[Test]
    public function it_handles_complex_meta_data(): void
    {
        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Complex Place',
                'category_slug' => 'restaurant',
                'rating' => 4.5,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'meta' => [
                    'user_ratings_total' => 500,
                    'types' => ['restaurant', 'food', 'point_of_interest'],
                    'price_level' => 2,
                    'wheelchair_accessible_entrance' => true
                ],
                'opening_hours' => ['monday' => '9:00-22:00']
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $this->service->fetchAndStore(52.2297, 21.0122);

        $place = Place::where('google_place_id', 'google123')->first();

        $this->assertNotNull($place);
        $this->assertEquals('Complex Place', $place->name);

        $meta = $place->meta;
        $this->assertIsArray($meta);
        $this->assertEquals(500, $meta['user_ratings_total']);
        $this->assertTrue($meta['wheelchair_accessible_entrance']);
        $this->assertEquals(2, $meta['price_level']);
        $this->assertEquals('google', $meta['source']);
        $this->assertContains('restaurant', $meta['google_types']);
    }

    #[Test]
    public function it_handles_string_meta_data()
    {
        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Test Place',
                'category_slug' => 'restaurant',
                'rating' => 4.5,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'meta' => [
                    'user_ratings_total' => 300,
                    'price_level' => 1,
                    'types' => ['restaurant', 'food', 'point_of_interest', 'establishment']
                ]
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $this->service->fetchAndStore(52.2297, 21.0122);

        $place = Place::where('google_place_id', 'google123')->first();
        $this->assertNotNull($place, 'Place was not created');

        $meta = $place->meta;
        $this->assertIsArray($meta, 'Meta should be an array');

        $this->assertArrayHasKey('user_ratings_total', $meta, 'user_ratings_total should exist in meta');
        $this->assertArrayHasKey('price_level', $meta, 'price_level should exist in meta');

        $this->assertEquals(300, $meta['user_ratings_total']);
        $this->assertEquals(1, $meta['price_level']);
    }

    #[Test]
    public function it_handles_null_meta_data()
    {
        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'No Meta Place',
                'category_slug' => 'restaurant',
                'rating' => 4.0,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'meta' => null
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $this->service->fetchAndStore(52.2297, 21.0122);

        $place = Place::where('google_place_id', 'google123')->first();

        $this->assertNotNull($place);
        $meta = $place->meta;
        $this->assertIsArray($meta);
        $this->assertEquals('google', $meta['source']);
        $this->assertArrayHasKey('google_types', $meta);
    }

    #[Test]
    public function it_normalizes_google_types(): void
    {
        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Mixed Case Place',
                'category_slug' => 'RESTAURANT',
                'rating' => 4.0,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'meta' => [
                    'types' => ['RESTAURANT', 'Food', '  point_of_interest  ', ''] // Mixed case and whitespace
                ]
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $this->service->fetchAndStore(52.2297, 21.0122);

        $place = Place::where('google_place_id', 'google123')->first();
        $meta = $place->meta;

        $googleTypes = $meta['google_types'];
        $this->assertContains('restaurant', $googleTypes);
        $this->assertContains('food', $googleTypes);
        $this->assertContains('point_of_interest', $googleTypes);
        $this->assertNotContains('', $googleTypes); // Empty string should be filtered out
    }

    #[Test]
    public function it_uses_default_radius(): void
    {
        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Test Place',
                'category_slug' => 'restaurant',
                'rating' => 4.0,
                'lat' => 52.2297,
                'lon' => 21.0122
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->with(52.2297, 21.0122, 3000) // Default radius
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $this->service->fetchAndStore(52.2297, 21.0122);
    }

    #[Test]
    public function it_handles_empty_google_response(): void
    {
        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn([]);

        Log::shouldReceive('info')
            ->once()
            ->with('[PlacesSyncService] Synced places', ['added' => 0, 'updated' => 0]);

        $result = $this->service->fetchAndStore(52.2297, 21.0122);

        $this->assertEquals(0, $result['added']);
        $this->assertEquals(0, $result['updated']);

        $this->assertDatabaseCount('places', 0);
    }

    #[Test]
    public function it_stores_location_as_geography(): void
    {
        $googlePlaces = [
            [
                'place_id' => 'google123',
                'name' => 'Test Place',
                'category_slug' => 'restaurant',
                'rating' => 4.0,
                'lat' => 52.2297,
                'lon' => 21.0122
            ]
        ];

        $this->googlePlaces
            ->expects($this->once())
            ->method('fetchNearby')
            ->willReturn($googlePlaces);

        Log::shouldReceive('info')->once();

        $this->service->fetchAndStore(52.2297, 21.0122);

        $place = Place::where('google_place_id', 'google123')->first();

        // Verify location is stored correctly (can't easily test geography type directly, but we can verify it's not null)
        $this->assertNotNull($place->location);

        // Verify we can query by location
        $nearbyPlaces = Place::whereRaw("ST_DWithin(location, ST_SetSRID(ST_MakePoint(21.0122, 52.2297), 4326), 100)")->get();
        $this->assertCount(1, $nearbyPlaces);
    }
}
