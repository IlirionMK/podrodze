<?php

namespace Tests\TestCase;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripItinerary;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase as BaseTestCase;

class ModelTestCase extends BaseTestCase
{
    /**
     * Create a test user with the given attributes.
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Test User ' . Str::random(5),
            'email' => 'test_' . Str::random(10) . '@example.com',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    /**
     * Create a test place with the given attributes.
     */
    protected function createPlace(array $attributes = []): Place
    {
        $place = Place::factory()->create(array_merge([
            'name' => 'Test Place ' . Str::random(5),
            'google_place_id' => 'test_place_' . Str::random(10),
            'category_slug' => 'test-category',
            'rating' => 4.5,
            'meta' => ['key' => 'value'],
            'opening_hours' => ['monday' => '9:00-17:00'],
        ], $attributes));
        
        // Set location using PostGIS
        DB::statement("UPDATE places SET location = ST_GeomFromText('POINT(0 0)', 4326) WHERE id = ?", [$place->id]);
        
        return $place;
    }

    /**
     * Create a test trip with the given attributes or user ID.
     *
     * @param  array|int  $attributesOrUserId  Either an array of attributes or a user ID for the owner
     * @param  array  $attributes  Additional attributes (only used if first parameter is user ID)
     * @return \App\Models\Trip
     */
    protected function createTrip($attributesOrUserId = [], array $attributes = []): Trip
    {
        if (is_int($attributesOrUserId)) {
            $attributes['owner_id'] = $attributesOrUserId;
        } else {
            $attributes = is_array($attributesOrUserId) ? $attributesOrUserId : [];
        }

        $defaults = [
            'name' => 'Test Trip ' . Str::random(5),
            'description' => 'Test Description',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(7),
        ];

        if (!isset($attributes['owner_id'])) {
            $user = $this->createUser();
            $defaults['owner_id'] = $user->id;
        }

        return Trip::factory()->create(array_merge($defaults, $attributes));
    }

    /**
     * Create a test activity log with the given attributes.
     */
    protected function createActivityLog(array $attributes = []): ActivityLog
    {
        if (!isset($attributes['user_id'])) {
            $user = $this->createUser();
            $attributes['user_id'] = $user->id;
        }

        return ActivityLog::factory()->create(array_merge([
            'action' => 'test_action_' . Str::random(5),
            'details' => ['description' => 'Test description ' . Str::random(10)],
            'target_type' => 'test',
            'target_id' => 1,
        ], $attributes));
    }

    /**
     * Create a test category with the given attributes.
     */
    protected function createCategory(array $attributes = []): Category
    {
        if (!isset($attributes['slug'])) {
            $name = $attributes['name'] ?? 'Test Category ' . Str::random(5);
            $attributes['slug'] = Str::slug($name);
        }

        return Category::factory()->create(array_merge([
            'slug' => 'test-category-' . Str::random(5),
            'translations' => ['en' => 'Test Category ' . Str::random(5)],
            'include_in_preferences' => true,
        ], $attributes));
    }

    /**
     * Create a test trip itinerary with the given attributes.
     */
    protected function createTripItinerary(array $attributes = []): TripItinerary
    {
        if (!isset($attributes['trip_id'])) {
            $trip = $this->createTrip();
            $attributes['trip_id'] = $trip->id;
        }

        if (!isset($attributes['schedule'])) {
            $attributes['schedule'] = [
                'days' => [
                    [
                        'date' => now()->format('Y-m-d'),
                        'activities' => [
                            [
                                'time' => '09:00',
                                'description' => 'Test activity',
                                'place_id' => null,
                            ]
                        ]
                    ]
                ]
            ];
        }

        return TripItinerary::factory()->create(array_merge([
            'schedule' => $attributes['schedule'],
            'day_count' => $attributes['day_count'] ?? 1,
            'generated_at' => now(),
        ], $attributes));
    }

    /**
     * Create test user preferences with the given attributes.
     */
    protected function createUserPreference(array $attributes = []): UserPreference
    {
        if (!isset($attributes['user_id'])) {
            $user = $this->createUser();
            $attributes['user_id'] = $user->id;
        }

        if (!isset($attributes['category_id'])) {
            $category = $this->createCategory();
            $attributes['category_id'] = $category->id;
        }

        return UserPreference::factory()->create(array_merge([
            'score' => 1,
        ], $attributes));
    }
}
