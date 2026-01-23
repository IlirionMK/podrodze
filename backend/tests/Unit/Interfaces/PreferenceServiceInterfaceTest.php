<?php

namespace Tests\Unit\Interfaces;

use App\DTO\Preference\Preference;
use App\Interfaces\PreferenceServiceInterface;
use App\Models\User;
use Mockery;
use Tests\TestCase;

class PreferenceServiceInterfaceTest extends TestCase
{
    private PreferenceServiceInterface $preferenceService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preferenceService = Mockery::mock(PreferenceServiceInterface::class);
        $this->user = User::factory()->make();
    }

    public function test_get_preferences_returns_preference_object()
    {
        $categories = [
            ['slug' => 'museum', 'name' => 'Museum'],
            ['slug' => 'food', 'name' => 'Food'],
            ['slug' => 'nature', 'name' => 'Nature']
        ];
        $user = ['preferred_categories' => ['museum', 'food']];

        $preference = new Preference($categories, $user);

        $this->preferenceService->shouldReceive('getPreferences')
            ->once()
            ->with($this->user)
            ->andReturn($preference);

        $result = $this->preferenceService->getPreferences($this->user);

        $this->assertInstanceOf(Preference::class, $result);
        $this->assertIsArray($result->categories);
        $this->assertArrayHasKey('preferred_categories', $result->user);
    }

    public function test_update_preferences()
    {
        $categories = [
            ['slug' => 'museum', 'name' => 'Museum'],
            ['slug' => 'food', 'name' => 'Food']
        ];
        $userPreferences = ['preferred_categories' => ['museum']];

        $expectedPreference = new Preference($categories, $userPreferences);

        $this->preferenceService->shouldReceive('updatePreferences')
            ->once()
            ->with($this->user, $userPreferences)
            ->andReturn($expectedPreference);

        $result = $this->preferenceService->updatePreferences($this->user, $userPreferences);

        $this->assertInstanceOf(Preference::class, $result);
        $this->assertIsArray($result->categories);
        $this->assertIsArray($result->user);
        $this->assertArrayHasKey('preferred_categories', $result->user);
    }
}
