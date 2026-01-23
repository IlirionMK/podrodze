<?php

namespace Tests\Unit\Http\Requests\Ai;

use App\Http\Requests\Ai\TripPlaceSuggestionsRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripPlaceSuggestionsRequestTest extends TestCase
{
    #[Test]
    public function it_authorizes_request(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function it_returns_correct_validation_rules(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('based_on_place_id', $rules);
        $this->assertArrayHasKey('limit', $rules);
        $this->assertArrayHasKey('radius_m', $rules);
        $this->assertArrayHasKey('locale', $rules);
        
        $basedOnPlaceIdRules = $rules['based_on_place_id'];
        $this->assertContains('nullable', $basedOnPlaceIdRules);
        $this->assertContains('integer', $basedOnPlaceIdRules);
        $this->assertContains('exists:places,id', $basedOnPlaceIdRules);

        $limitRules = $rules['limit'];
        $this->assertContains('nullable', $limitRules);
        $this->assertContains('integer', $limitRules);
        $this->assertContains('min:1', $limitRules);

        $radiusMRules = $rules['radius_m'];
        $this->assertContains('nullable', $radiusMRules);
        $this->assertContains('integer', $radiusMRules);

        $localeRules = $rules['locale'];
        $this->assertContains('nullable', $localeRules);
        $this->assertContains('string', $localeRules);
        $this->assertContains('max:10', $localeRules);
    }

    #[Test]
    public function it_validates_based_on_place_id_must_exist_in_places_table(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator(['based_on_place_id' => 999], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('based_on_place_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_limit_must_be_at_least_1(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator(['limit' => 0], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('limit', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_limit_must_not_exceed_max_limit(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator(['limit' => 21], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('limit', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_radius_m_must_be_at_least_min_radius(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator(['radius_m' => 199], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('radius_m', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_radius_m_must_not_exceed_max_radius(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator(['radius_m' => 50001], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('radius_m', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_locale_max_length(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator(['locale' => 'very_long_locale_string'], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_validation_with_valid_data(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator([
            'limit' => 10,
            'radius_m' => 5000,
            'locale' => 'en',
        ], $request->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_validation_with_null_optional_fields(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator([], $request->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_validation_with_partial_data(): void
    {
        $request = new TripPlaceSuggestionsRequest();
        
        $validator = validator(['limit' => 5], $request->rules());
        $this->assertTrue($validator->passes());
        
        $validator = validator(['radius_m' => 1000], $request->rules());
        $this->assertTrue($validator->passes());
        
        $validator = validator(['locale' => 'pl'], $request->rules());
        $this->assertTrue($validator->passes());
    }
}
