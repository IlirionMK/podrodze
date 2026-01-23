<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\CategoryNormalizer;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryNormalizerTest extends TestCase
{
    private CategoryNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new CategoryNormalizer();
    }

    #[Test]
    public function it_normalizes_category_using_config_mapping(): void
    {
        Config::set('place_categories', [
            'restaurant' => 'food',
            'museum' => 'museum',
            'park' => 'nature'
        ]);

        $this->assertEquals('food', $this->normalizer->normalize('restaurant'));
        $this->assertEquals('museum', $this->normalizer->normalize('museum'));
        $this->assertEquals('nature', $this->normalizer->normalize('park'));
    }

    #[Test]
    public function it_falls_back_to_other_when_no_mapping_exists(): void
    {
        Config::set('place_categories', [
            'restaurant' => 'food'
        ]);

        Config::set('place_categories.other', 'other');

        $this->assertEquals('other', $this->normalizer->normalize('unknown_category'));
        $this->assertEquals('other', $this->normalizer->normalize('nonexistent'));
    }

    #[Test]
    public function it_falls_back_to_other_when_config_is_missing(): void
    {
        Config::set('place_categories', []);

        $this->assertEquals('other', $this->normalizer->normalize('anything'));
    }

    #[Test]
    public function it_handles_null_input(): void
    {
        Config::set('place_categories.other', 'other');

        $this->assertEquals('other', $this->normalizer->normalize(null));
    }

    #[Test]
    public function it_trims_and_lowercases_input(): void
    {
        Config::set('place_categories', [
            'restaurant' => 'food'
        ]);

        $this->assertEquals('food', $this->normalizer->normalize(' RESTAURANT '));
        $this->assertEquals('food', $this->normalizer->normalize('Restaurant'));
        $this->assertEquals('food', $this->normalizer->normalize('RESTAURANT'));
    }

    #[Test]
    public function it_handles_empty_string(): void
    {
        Config::set('place_categories.other', 'other');

        $this->assertEquals('other', $this->normalizer->normalize(''));
        $this->assertEquals('other', $this->normalizer->normalize('   '));
    }

    #[Test]
    public function it_checks_if_category_is_recommendable(): void
    {
        $this->assertTrue($this->normalizer->isRecommendable('food'));
        $this->assertTrue($this->normalizer->isRecommendable('nightlife'));
        $this->assertTrue($this->normalizer->isRecommendable('museum'));
        $this->assertTrue($this->normalizer->isRecommendable('nature'));
        $this->assertTrue($this->normalizer->isRecommendable('attraction'));
        $this->assertTrue($this->normalizer->isRecommendable('other'));

        $this->assertFalse($this->normalizer->isRecommendable('unknown'));
        $this->assertFalse($this->normalizer->isRecommendable('shopping'));
        $this->assertFalse($this->normalizer->isRecommendable('business'));
    }

    #[Test]
    public function it_handles_complex_real_world_categories(): void
    {
        Config::set('place_categories', [
            'restaurant' => 'food',
            'cafe' => 'food',
            'bar' => 'nightlife',
            'night_club' => 'nightlife',
            'art_gallery' => 'museum',
            'museum' => 'museum',
            'park' => 'nature',
            'natural_feature' => 'nature',
            'tourist_attraction' => 'attraction',
            'amusement_park' => 'attraction'
        ]);

        Config::set('place_categories.other', 'other');

        // Test various Google Places API categories
        $this->assertEquals('food', $this->normalizer->normalize('restaurant'));
        $this->assertEquals('food', $this->normalizer->normalize('cafe'));
        $this->assertEquals('nightlife', $this->normalizer->normalize('bar'));
        $this->assertEquals('nightlife', $this->normalizer->normalize('night_club'));
        $this->assertEquals('museum', $this->normalizer->normalize('art_gallery'));
        $this->assertEquals('nature', $this->normalizer->normalize('park'));
        $this->assertEquals('attraction', $this->normalizer->normalize('tourist_attraction'));
        
        // Test fallback
        $this->assertEquals('other', $this->normalizer->normalize('shopping_mall'));
        $this->assertEquals('other', $this->normalizer->normalize('bank'));
    }

    #[Test]
    public function it_preserves_case_sensitivity_in_mapping(): void
    {
        Config::set('place_categories', [
            'restaurant' => 'food', // keys are expected to be lowercase in the mapping
            'museum' => 'museum'    // as the service converts input to lowercase
        ]);

        // The service converts input to lowercase before lookup
        $this->assertEquals('food', $this->normalizer->normalize('Restaurant'));
        $this->assertEquals('food', $this->normalizer->normalize('restaurant'));
        $this->assertEquals('museum', $this->normalizer->normalize('Museum'));
        $this->assertEquals('museum', $this->normalizer->normalize('MUSEUM'));
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        Config::set('place_categories', [
            'café' => 'food',
            'müze' => 'museum'
        ]);

        Config::set('place_categories.other', 'other');

        $this->assertEquals('food', $this->normalizer->normalize('café'));
        $this->assertEquals('museum', $this->normalizer->normalize('müze'));
        $this->assertEquals('other', $this->normalizer->normalize('café restaurant')); // No exact match
    }

    #[Test]
    public function it_returns_configured_other_category(): void
    {
        Config::set('place_categories', []);
        Config::set('place_categories.other', 'fallback');

        $this->assertEquals('fallback', $this->normalizer->normalize('anything'));
        $this->assertEquals('fallback', $this->normalizer->normalize(null));
        $this->assertEquals('fallback', $this->normalizer->normalize(''));
    }

    #[Test]
    public function it_handles_numeric_strings(): void
    {
        Config::set('place_categories', [
            '123' => 'food'
        ]);

        Config::set('place_categories.other', 'other');

        $this->assertEquals('food', $this->normalizer->normalize('123'));
        $this->assertEquals('other', $this->normalizer->normalize('456'));
    }
}
