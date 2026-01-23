<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\UserMiniResource;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserMiniResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_user_to_minimal_representation(): void
    {
        $user = User::factory()->create([
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $resource = new UserMiniResource($user);
        $result = $resource->toArray(request());

        $this->assertEquals(123, $result['id']);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    #[Test]
    public function it_handles_null_values(): void
    {
        $user = new User();
        $user->id = null;
        $user->name = null;
        $user->email = null;

        $resource = new UserMiniResource($user);
        $result = $resource->toArray(request());

        $this->assertNull($result['id']);
        $this->assertNull($result['name']);
        $this->assertNull($result['email']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $user = User::factory()->create();

        $resource = new UserMiniResource($user);
        $result = $resource->toArray(request());

        $expectedKeys = ['id', 'name', 'email'];
        $this->assertEquals($expectedKeys, array_keys($result));
    }
}
