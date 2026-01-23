<?php

namespace Tests\Unit\Services;

use App\DTO\Preference\Preference;
use App\Models\Category;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Activity\ActivityLogger;
use App\Services\PreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class PreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private PreferenceService $service;
    private MockObject|ActivityLogger $activityLogger;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityLogger = $this->createMock(ActivityLogger::class);
        $this->service = new PreferenceService($this->activityLogger);
        $this->user = User::factory()->create();

        $this->foodCategory = Category::factory()->create(['slug' => 'food', 'translations' => ['en' => 'Food', 'pl' => 'Jedzenie']]);
        $this->museumCategory = Category::factory()->create(['slug' => 'museum', 'translations' => ['en' => 'Museum', 'pl' => 'Muzeum']]);
        $this->natureCategory = Category::factory()->create(['slug' => 'nature', 'translations' => ['en' => 'Nature', 'pl' => 'Natura']]);
    }

    #[Test]
    public function it_gets_user_preferences_with_categories(): void
    {
        // Create user preferences
        UserPreference::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->museumCategory->id,
            'score' => 1
        ]);

        $preference = $this->service->getPreferences($this->user);

        $this->assertInstanceOf(Preference::class, $preference);

        $categories = $preference->categories;
        $this->assertCount(3, $categories);

        $foodCategory = collect($categories)->firstWhere('slug', 'food');
        $this->assertEquals('Food', $foodCategory['name']);

        $scores = $preference->user;
        $this->assertEquals(2, $scores['food']);
        $this->assertEquals(1, $scores['museum']);
        $this->assertArrayNotHasKey('nature', $scores);
    }

    #[Test]
    public function it_returns_empty_preferences_for_new_user(): void
    {
        $preference = $this->service->getPreferences($this->user);

        $this->assertInstanceOf(Preference::class, $preference);
        $this->assertCount(3, $preference->categories);
        $this->assertEmpty($preference->user);
    }

    #[Test]
    public function it_updates_user_preferences(): void
    {
        $preferences = [
            'food' => 2,
            'museum' => 1,
            'nature' => 0
        ];

        $this->activityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->user,
                'user.preferences_updated',
                $this->user,
                $this->callback(fn($details) =>
                    $details['user_id'] === $this->user->id &&
                    isset($details['changes']) &&
                    empty($details['ignored'])
                )
            );

        $updated = $this->service->updatePreferences($this->user, $preferences);

        $this->assertInstanceOf(Preference::class, $updated);
        $scores = $updated->user;
        $this->assertEquals(2, $scores['food']);
        $this->assertEquals(1, $scores['museum']);
        $this->assertEquals(0, $scores['nature']);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => Category::where('slug', 'food')->first()->id,
            'score' => 2
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->museumCategory->id,
            'score' => 1
        ]);
    }

    #[Test]
    public function it_creates_new_preferences(): void
    {
        // Create initial preferences
        UserPreference::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->foodCategory->id,
            'score' => 1
        ]);

        $preferences = [
            'food' => 2, // Update existing
            'museum' => 1 // Create new
        ];

        $this->activityLogger
            ->expects($this->once())
            ->method('add');

        $updated = $this->service->updatePreferences($this->user, $preferences);

        $scores = $updated->user;
        $this->assertEquals(2, $scores['food']);
        $this->assertEquals(1, $scores['museum']);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $this->museumCategory->id,
            'score' => 1
        ]);
    }

    #[Test]
    public function it_validates_preference_scores(): void
    {
        $preferences = [
            'food' => 5, // Should be clamped to 2
            'museum' => -1, // Should be clamped to 0
            'nature' => 1 // Valid
        ];

        $this->activityLogger
            ->expects($this->once())
            ->method('add');

        $updated = $this->service->updatePreferences($this->user, $preferences);

        $scores = $updated->user;
        $this->assertEquals(2, $scores['food']); // Clamped
        $this->assertEquals(0, $scores['museum']); // Clamped
        $this->assertEquals(1, $scores['nature']); // Valid
    }

    #[Test]
    public function it_ignores_invalid_categories(): void
    {
        $preferences = [
            'food' => 2,
            'invalid_category' => 1,
            'another_invalid' => 0
        ];

        $this->activityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(fn($details) =>
                    !empty($details['ignored']) &&
                    in_array('invalid_category', $details['ignored']) &&
                    in_array('another_invalid', $details['ignored'])
                )
            );

        $updated = $this->service->updatePreferences($this->user, $preferences);

        $scores = $updated->user;
        $this->assertEquals(2, $scores['food']);
        $this->assertArrayNotHasKey('invalid_category', $scores);
        $this->assertArrayNotHasKey('another_invalid', $scores);
    }

    #[Test]
    public function it_handles_empty_preferences_array(): void
    {
        $originalPreference = $this->service->getPreferences($this->user);

        $this->activityLogger
            ->expects($this->never())
            ->method('add');

        $updated = $this->service->updatePreferences($this->user, []);

        $categories = $updated->categories;
        $this->assertEquals($originalPreference->categories, $updated->categories);
        $this->assertEquals($originalPreference->user, $updated->user);
    }

    #[Test]
    public function it_logs_activity_only_when_there_are_changes(): void
    {
        // Create initial preferences
        UserPreference::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => Category::where('slug', 'food')->first()->id,
            'score' => 2
        ]);

        // No changes
        $this->activityLogger
            ->expects($this->never())
            ->method('add');

        $updated = $this->service->updatePreferences($this->user, ['food' => 2]);

        // With changes
        $newActivityLogger = $this->createMock(ActivityLogger::class);
        $newService = new PreferenceService($newActivityLogger);
        
        $preferences = ['food' => 1];
        $newActivityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(fn($details) =>
                    $details['user_id'] === $this->user->id &&
                    $details['changes']['food']['before'] === 2 &&
                    $details['changes']['food']['after'] === 1
                )
            );

        $newService->updatePreferences($this->user, $preferences);
    }

    #[Test]
    public function it_tracks_changes_correctly(): void
    {
        UserPreference::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->foodCategory->id,
            'score' => 1
        ]);

        $preferences = [
            'food' => 2, // Changed
            'museum' => 1 // New
        ];

        $this->activityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(fn($details) =>
                    $details['changes']['food']['before'] === 1 &&
                    $details['changes']['food']['after'] === 2 &&
                    $details['changes']['museum']['before'] === null &&
                    $details['changes']['museum']['after'] === 1
                )
            );

        $this->service->updatePreferences($this->user, $preferences);
    }

    #[Test]
    public function it_handles_whitespace_in_category_slugs(): void
    {
        $preferences = [
            ' food ' => 2, // Should be trimmed
            'museum' => 1
        ];

        $this->activityLogger
            ->expects($this->once())
            ->method('add');

        $updated = $this->service->updatePreferences($this->user, $preferences);

        $scores = $updated->user;
        $this->assertEquals(2, $scores['food']); // Trimmed
        $this->assertEquals(1, $scores['museum']);
    }

    #[Test]
    public function it_ignores_empty_category_slugs(): void
    {
        $preferences = [
            '' => 2, // Empty slug
            '   ' => 1, // Whitespace only
            'food' => 1
        ];

        $this->activityLogger
            ->expects($this->once())
            ->method('add');

        $updated = $this->service->updatePreferences($this->user, $preferences);

        $scores = $updated->user;
        $this->assertEquals(1, $scores['food']); // Should be 1, not 2
        $this->assertArrayNotHasKey('', $scores);
    }

    #[Test]
    public function it_returns_fresh_preferences_after_update(): void
    {
        $preferences = ['food' => 2];

        $this->activityLogger
            ->expects($this->once())
            ->method('add');

        $updated = $this->service->updatePreferences($this->user, $preferences);

        // Verify it's a fresh instance with updated data
        $scores = $updated->user;
        $this->assertEquals(2, $scores['food']);
        $this->assertCount(3, $updated->categories); // All categories should be present
    }
}
