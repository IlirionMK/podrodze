<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Models\Category;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PreferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    protected string $baseUrl = '/api/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

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

    public function test_it_gets_user_preferences()
    {
        $category1 = $this->createCategory(['slug' => 'restaurants']);
        $category2 = $this->createCategory(['slug' => 'museums']);
        $this->createCategory(['slug' => 'parks']);

        (new UserPreference([
            'user_id' => $this->user->getKey(),
            'category_id' => $category1->getKey(),
            'score' => 2
        ]))->save();

        (new UserPreference([
            'user_id' => $this->user->getKey(),
            'category_id' => $category2->getKey(),
            'score' => 1
        ]))->save();

        $response = $this->getJson($this->baseUrl . '/preferences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'categories' => [
                        '*' => ['slug', 'name']
                    ],
                    'user' => []
                ]
            ]);

        $responseData = $response->json('data');
        $userScores = $responseData['user'];

        $this->assertArrayHasKey('restaurants', $userScores);
        $this->assertArrayHasKey('museums', $userScores);
        $this->assertEquals(2, $userScores['restaurants']);
        $this->assertEquals(1, $userScores['museums']);

        if (array_key_exists('parks', $userScores)) {
            $this->assertEquals(0, $userScores['parks']);
        }
    }

    public function test_it_updates_user_preferences()
    {
        $category1 = $this->createCategory(['slug' => 'restaurants']);
        $category2 = $this->createCategory(['slug' => 'museums']);
        $this->createCategory(['slug' => 'parks']);

        $response = $this->putJson($this->baseUrl . '/users/me/preferences', [
            'preferences' => [
                'restaurants' => 2,
                'museums' => 1,
                'parks' => 0,
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'categories' => [
                        '*' => ['slug', 'name']
                    ],
                    'user' => []
                ]
            ]);

        $responseData = $response->json('data');
        $userScores = $responseData['user'];

        $this->assertEquals(2, $userScores['restaurants']);
        $this->assertEquals(1, $userScores['museums']);
        $this->assertEquals(0, $userScores['parks']);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->getKey(),
            'category_id' => $category1->getKey(),
            'score' => 2
        ]);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->getKey(),
            'category_id' => $category2->getKey(),
            'score' => 1
        ]);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->getKey(),
            'score' => 0
        ]);
    }

    public function test_it_validates_preference_scores()
    {
        $category = $this->createCategory(['slug' => 'test-category']);
        $response = $this->putJson($this->baseUrl . '/users/me/preferences', [
            'preferences' => [
                'test-category' => -1,
            ]
        ]);
        $response->assertStatus(422);
        $response = $this->putJson($this->baseUrl . '/users/me/preferences', [
            'preferences' => [
                'test-category' => 3,
            ]
        ]);
        $response->assertStatus(422);
        $response = $this->putJson($this->baseUrl . '/users/me/preferences', [
            'preferences' => [
                'test-category' => 'high',
            ]
        ]);
        $response->assertStatus(422);
    }

    public function test_it_handles_invalid_category_slugs()
    {
        $validCategory = $this->createCategory(['slug' => 'valid-category']);

        $response = $this->putJson($this->baseUrl . '/users/me/preferences', [
            'preferences' => [
                'valid-category' => 2,
                'nonexistent-category' => 2,
            ]
        ]);

        $response->assertStatus(200);

        $this->assertEquals(1, UserPreference::where('user_id', $this->user->getKey())->count());
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->getKey(),
            'category_id' => $validCategory->getKey(),
            'score' => 2
        ]);
    }

    public function test_it_requires_authentication()
    {
        $response = $this->getJson($this->baseUrl . '/preferences');
        $response->assertStatus(200);
        $category = $this->createCategory(['slug' => 'test-category']);
        $response = $this->putJson($this->baseUrl . '/users/me/preferences', [
            'preferences' => [
                'test-category' => 1
            ]
        ]);
        $response->assertStatus(200);
    }
}
