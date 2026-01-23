<?php

namespace Tests\Unit\Http\Requests\Admin;

use App\Http\Requests\Admin\UpdateUserBanRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateUserBanRequestTest extends TestCase
{
    #[Test]
    public function it_authorizes_request(): void
    {
        $request = new UpdateUserBanRequest();
        
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function it_returns_correct_validation_rules(): void
    {
        $request = new UpdateUserBanRequest();
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('banned', $rules);
        
        $bannedRules = $rules['banned'];
        $this->assertContains('required', $bannedRules);
        $this->assertContains('boolean', $bannedRules);
    }

    #[Test]
    public function it_validates_banned_field_is_required(): void
    {
        $request = new UpdateUserBanRequest();
        
        $validator = validator([], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('banned', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_banned_field_must_be_boolean(): void
    {
        $request = new UpdateUserBanRequest();
        
        $validator = validator(['banned' => 'not_boolean'], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('banned', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_validation_with_valid_data(): void
    {
        $request = new UpdateUserBanRequest();
        
        $validator = validator(['banned' => true], $request->rules());
        $this->assertTrue($validator->passes());
        
        $validator = validator(['banned' => false], $request->rules());
        $this->assertTrue($validator->passes());
    }
}
