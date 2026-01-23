<?php

namespace Tests\Unit\Http\Requests\Admin;

use App\Http\Requests\Admin\UpdateUserRoleRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateUserRoleRequestTest extends TestCase
{
    #[Test]
    public function it_authorizes_request(): void
    {
        $request = new UpdateUserRoleRequest();
        
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function it_returns_correct_validation_rules(): void
    {
        $request = new UpdateUserRoleRequest();
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('role', $rules);
        
        $roleRules = $rules['role'];
        $this->assertContains('required', $roleRules);
        $this->assertContains('string', $roleRules);
    }

    #[Test]
    public function it_validates_role_field_is_required(): void
    {
        $request = new UpdateUserRoleRequest();
        
        $validator = validator([], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_role_field_must_be_string(): void
    {
        $request = new UpdateUserRoleRequest();
        
        $validator = validator(['role' => 123], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_role_must_be_in_allowed_values(): void
    {
        $request = new UpdateUserRoleRequest();
        
        $validator = validator(['role' => 'invalid_role'], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_validation_with_valid_user_role(): void
    {
        $request = new UpdateUserRoleRequest();
        
        $validator = validator(['role' => 'user'], $request->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_validation_with_valid_admin_role(): void
    {
        $request = new UpdateUserRoleRequest();
        
        $validator = validator(['role' => 'admin'], $request->rules());
        $this->assertTrue($validator->passes());
    }
}
