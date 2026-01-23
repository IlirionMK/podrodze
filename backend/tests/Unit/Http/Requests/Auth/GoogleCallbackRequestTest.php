<?php

namespace Tests\Unit\Http\Requests\Auth;

use App\Http\Requests\Auth\GoogleCallbackRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleCallbackRequestTest extends TestCase
{
    private GoogleCallbackRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new GoogleCallbackRequest();
    }

    #[Test]
    public function it_authorizes_request(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    #[Test]
    public function it_returns_correct_validation_rules(): void
    {
        $expectedRules = [
            'code' => ['required', 'string'],
        ];

        $this->assertEquals($expectedRules, $this->request->rules());
    }

    #[Test]
    public function it_passes_validation_with_valid_data(): void
    {
        $data = ['code' => 'test_auth_code'];

        $validator = validator($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_fails_validation_when_code_is_missing(): void
    {
        $data = [];

        $validator = validator($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_validation_when_code_is_not_string(): void
    {
        $data = ['code' => 12345];

        $validator = validator($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
    }
}
