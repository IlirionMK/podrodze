<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\User;
use App\Models\UserPreference;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\ModelTestCase;

class UserPreferenceTest extends ModelTestCase
{
    #[Test]
    public function it_has_required_fields()
    {
        $user = $this->createUser();
        $category = $this->createCategory();
        
        $preferences = $this->createUserPreference([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'score' => 5,
        ]);

        $this->assertEquals($user->id, $preferences->user_id);
        $this->assertEquals($category->id, $preferences->category_id);
        $this->assertEquals(5, $preferences->score);
    }

    #[Test]
    public function it_belongs_to_user()
    {
        $user = $this->createUser();
        $preferences = $this->createUserPreference(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $preferences->user);
        $this->assertEquals($user->id, $preferences->user->id);
    }

    #[Test]
    public function it_belongs_to_category()
    {
        $category = $this->createCategory();
        $preferences = $this->createUserPreference(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $preferences->category);
        $this->assertEquals($category->id, $preferences->category->id);
    }

    #[Test]
    public function it_has_default_score()
    {
        $preferences = $this->createUserPreference();
        $this->assertEquals(1, $preferences->score);
    }
}
