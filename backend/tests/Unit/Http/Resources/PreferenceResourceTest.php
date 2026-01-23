<?php

namespace Tests\Unit\Http\Resources;

use App\DTO\Preference\Preference;
use App\Http\Resources\PreferenceResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreferenceResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_preference_to_array(): void
    {
        $preference = new Preference(
            categories: ['museums', 'restaurants', 'parks'],
            user: [
                'notifications' => true,
                'theme' => 'dark',
                'language' => 'en',
            ]
        );

        $resource = new PreferenceResource($preference);
        $result = $resource->toArray(request());

        $this->assertEquals(['museums', 'restaurants', 'parks'], $result['categories']);
        $this->assertIsObject($result['user']);
        $this->assertTrue($result['user']->notifications);
        $this->assertEquals('dark', $result['user']->theme);
        $this->assertEquals('en', $result['user']->language);
    }

    #[Test]
    public function it_handles_empty_categories(): void
    {
        $preference = new Preference(
            categories: [],
            user: ['notifications' => false]
        );

        $resource = new PreferenceResource($preference);
        $result = $resource->toArray(request());

        $this->assertEmpty($result['categories']);
        $this->assertFalse($result['user']->notifications);
    }

    #[Test]
    public function it_handles_empty_user_preferences(): void
    {
        $preference = new Preference(
            categories: ['museums'],
            user: []
        );

        $resource = new PreferenceResource($preference);
        $result = $resource->toArray(request());

        $this->assertEquals(['museums'], $result['categories']);
        $this->assertIsObject($result['user']);
        $this->assertEmpty((array) $result['user']);
    }

    #[Test]
    public function it_handles_complex_user_preferences(): void
    {
        $preference = new Preference(
            categories: ['nightlife', 'shopping', 'outdoors'],
            user: [
                'notifications' => true,
                'theme' => 'light',
                'language' => 'pl',
                'currency' => 'PLN',
                'radius_km' => 50,
                'price_range' => 'medium',
                'accessibility' => true,
            ]
        );

        $resource = new PreferenceResource($preference);
        $result = $resource->toArray(request());

        $this->assertEquals(['nightlife', 'shopping', 'outdoors'], $result['categories']);
        $this->assertIsObject($result['user']);
        $this->assertTrue($result['user']->notifications);
        $this->assertEquals('light', $result['user']->theme);
        $this->assertEquals('pl', $result['user']->language);
        $this->assertEquals('PLN', $result['user']->currency);
        $this->assertEquals(50, $result['user']->radius_km);
        $this->assertEquals('medium', $result['user']->price_range);
        $this->assertTrue($result['user']->accessibility);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $preference = new Preference(
            categories: ['test'],
            user: ['key' => 'value']
        );

        $resource = new PreferenceResource($preference);
        $result = $resource->toArray(request());

        $expectedKeys = ['categories', 'user'];
        $this->assertEquals($expectedKeys, array_keys($result));
        $this->assertIsArray($result['categories']);
        $this->assertIsObject($result['user']);
    }

    #[Test]
    public function it_preserves_category_order(): void
    {
        $preference = new Preference(
            categories: ['restaurants', 'museums', 'parks', 'nightlife'],
            user: []
        );

        $resource = new PreferenceResource($preference);
        $result = $resource->toArray(request());

        $this->assertEquals(['restaurants', 'museums', 'parks', 'nightlife'], $result['categories']);
    }
}
