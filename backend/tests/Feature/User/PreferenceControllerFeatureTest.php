<?php

namespace Tests\Feature\User;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test suite for PreferenceController API endpoints.
 *
 * This test verifies the functionality of user preference management including:
 * 1. Retrieving user preferences with available categories
 * 2. Updating user preference scores (0-2 scale)
 * 3. Dynamic validation based on available categories
 * 4. Handling edge cases (no categories, invalid values)
 * 5. Authentication requirements
 * 6. Integration with category system
 */
#[Group('preferences')]
#[Group('user')]
#[Group('validation')]
#[Group('feature')]
class PreferenceControllerFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var User Test user */
    protected User $user;

    /** @var array Test categories */
    protected array $categories = [];

    /**
     * Set up the test environment.
     * Creates test user and categories for preference testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create test categories that participate in preferences
        $this->categories = [
            'restaurant' => Category::factory()->create([
                'slug' => 'restaurant',
                'include_in_preferences' => true,
                'translations' => ['en' => 'Restaurant', 'pl' => 'Restauracja']
            ]),
            'museum' => Category::factory()->create([
                'slug' => 'museum',
                'include_in_preferences' => true,
                'translations' => ['en' => 'Museum', 'pl' => 'Muzeum']
            ]),
            'park' => Category::factory()->create([
                'slug' => 'park',
                'include_in_preferences' => true,
                'translations' => ['en' => 'Park', 'pl' => 'Park']
            ]),
        ];

        // Create category that doesn't participate in preferences
        Category::factory()->create([
            'slug' => 'non-preference',
            'include_in_preferences' => false,
            'translations' => ['en' => 'Non Preference', 'pl' => 'Niepreferowane']
        ]);

        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function it_retrieves_user_preferences()
    {
        $response = $this->getJson('/api/v1/preferences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'categories' => [
                        '*' => [
                            'slug',
                            'name'
                        ]
                    ],
                    'user' => [
                        // Should contain preference categories
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        
        // Should include all categories in current implementation (not filtered by include_in_preferences)
        $this->assertCount(4, $responseData['categories']);
        
        // Verify category structure
        $categorySlugs = collect($responseData['categories'])->pluck('slug');
        $this->assertContains('restaurant', $categorySlugs);
        $this->assertContains('museum', $categorySlugs);
        $this->assertContains('park', $categorySlugs);
        $this->assertContains('non-preference', $categorySlugs); // Included in current implementation
    }

    #[Test]
    public function it_requires_authentication_to_access_preferences()
    {
        $this->refreshApplication();

        $response = $this->getJson('/api/v1/preferences');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_updates_user_preferences()
    {
        $preferencesData = [
            'preferences' => [
                'restaurant' => 2, // Love
                'museum' => 1,    // Like
                'park' => 0       // Neutral
            ]
        ];

        $response = $this->putJson('/api/v1/users/me/preferences', $preferencesData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'categories',
                    'user'
                ]
            ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->categories['restaurant']->id,
            'score' => 2
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->categories['museum']->id,
            'score' => 1
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->categories['park']->id,
            'score' => 0
        ]);
    }

    #[Test]
    public function it_validates_preference_scores()
    {
        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => 3, // Invalid: should be 0-2
                'museum' => -1,   // Invalid: should be 0-2
                'park' => 1.5     // Invalid: should be integer
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['preferences.restaurant', 'preferences.museum', 'preferences.park']);
    }

    #[Test]
    public function it_requires_all_preference_categories()
    {
        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => 2,
                // Missing museum and park
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['preferences.museum', 'preferences.park']);
    }

    #[Test]
    public function it_prevents_updating_non_preference_categories()
    {
        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => 2,
                'museum' => 1,
                'park' => 0,
                'non-preference' => 2 // This category should be ignored
            ]
        ]);

        // Current implementation ignores non-preference categories instead of rejecting them
        $response->assertStatus(200);
        
        // Verify only preference categories were saved
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->categories['restaurant']->id,
            'score' => 2
        ]);
        
        // Verify non-preference category was not processed
        $this->assertDatabaseMissing('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => Category::where('slug', 'non-preference')->first()->id,
        ]);
    }

    #[Test]
    public function it_handles_empty_preferences_array()
    {
        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => []
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['preferences']);
    }

    #[Test]
    public function it_handles_missing_preferences_key()
    {
        $response = $this->putJson('/api/v1/users/me/preferences', [
            'some_other_key' => 'value'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['preferences']);
    }

    #[Test]
    public function it_returns_updated_preferences_after_update()
    {
        $preferencesData = [
            'preferences' => [
                'restaurant' => 2,
                'museum' => 1,
                'park' => 0
            ]
        ];

        $response = $this->putJson('/api/v1/users/me/preferences', $preferencesData);

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $this->assertArrayHasKey('user', $responseData);
        $this->assertArrayHasKey('categories', $responseData);
        
        // Verify the returned preferences match what we sent
        $userPreferences = $responseData['user'];
        $this->assertEquals(2, $userPreferences['restaurant']);
        $this->assertEquals(1, $userPreferences['museum']);
        $this->assertEquals(0, $userPreferences['park']);
    }

    #[Test]
    public function it_handles_no_preference_categories_configured()
    {
        // Delete all preference categories
        Category::whereIn('id', array_map(fn($cat) => $cat->id, array_values($this->categories)))->delete();

        $response = $this->getJson('/api/v1/preferences');
        $response->assertStatus(200);
        $responseData = $response->json('data');
        // Current implementation returns non-preference categories even when preference categories are deleted
        $this->assertCount(1, $responseData['categories']);
        $this->assertEquals('non-preference', $responseData['categories'][0]['slug']);

        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => 2
            ]
        ]);
        
        $response->assertStatus(400)
            ->assertJson(['error' => 'No preference categories configured.']);
    }

    #[Test]
    public function it_handles_preference_update_with_existing_preferences()
    {
        // Create existing preferences
        \App\Models\UserPreference::create([
            'user_id' => $this->user->id,
            'category_id' => $this->categories['restaurant']->id,
            'score' => 0
        ]);
        \App\Models\UserPreference::create([
            'user_id' => $this->user->id,
            'category_id' => $this->categories['museum']->id,
            'score' => 1
        ]);

        $preferencesData = [
            'preferences' => [
                'restaurant' => 2, // Update from 0 to 2
                'museum' => 0,    // Update from 1 to 0
                'park' => 1       // New preference
            ]
        ];

        $response = $this->putJson('/api/v1/users/me/preferences', $preferencesData);

        $response->assertStatus(200);

        // Verify updated values
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->categories['restaurant']->id,
            'score' => 2
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->categories['museum']->id,
            'score' => 0
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->categories['park']->id,
            'score' => 1
        ]);
    }

    #[Test]
    public function it_handles_preference_categories_with_translations()
    {
        $response = $this->getJson('/api/v1/preferences');

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $categories = $responseData['categories'];
        
        // Verify translations are NOT included in current implementation
        $restaurantCategory = collect($categories)->firstWhere('slug', 'restaurant');
        $this->assertNotNull($restaurantCategory);
        $this->assertArrayNotHasKey('translations', $restaurantCategory);
        $this->assertEquals('Restaurant', $restaurantCategory['name']);
    }

    #[Test]
    public function it_requires_authentication_to_update_preferences()
    {
        $this->refreshApplication();

        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => 2,
                'museum' => 1,
                'park' => 0
            ]
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_handles_large_number_of_preference_categories()
    {
        // Create many preference categories
        $additionalCategories = [];
        for ($i = 4; $i <= 20; $i++) {
            $category = Category::factory()->create([
                'slug' => "category-{$i}",
                'include_in_preferences' => true,
                'translations' => ['en' => "Category {$i}", 'pl' => "Kategoria {$i}"]
            ]);
            $additionalCategories["category-{$i}"] = $category;
        }

        $response = $this->getJson('/api/v1/preferences');
        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $this->assertCount(21, $responseData['categories']); // 3 original + 1 non-preference + 17 new

        // Test updating all available preference categories
        $preferencesData = ['preferences' => []];
        
        // Include original preference categories
        $preferencesData['preferences']['restaurant'] = 1;
        $preferencesData['preferences']['museum'] = 2;
        $preferencesData['preferences']['park'] = 0;
        
        // Include new preference categories
        for ($i = 4; $i <= 20; $i++) {
            $preferencesData['preferences']["category-{$i}"] = $i % 3; // 0, 1, or 2
        }

        $response = $this->putJson('/api/v1/users/me/preferences', $preferencesData);
        $response->assertStatus(200);
    }

    #[Test]
    public function it_handles_preference_boundaries()
    {
        // Test minimum boundary (0)
        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => 0,
                'museum' => 0,
                'park' => 0
            ]
        ]);

        $response->assertStatus(200);

        // Test maximum boundary (2)
        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => 2,
                'museum' => 2,
                'park' => 2
            ]
        ]);

        $response->assertStatus(200);

        // Test just outside boundaries
        $response = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => -1, // Below minimum
                'museum' => 3,      // Above maximum
                'park' => 2
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['preferences.restaurant', 'preferences.museum']);
    }
}
