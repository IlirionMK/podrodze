<?php

namespace Tests\Unit\DTO\Preference;

use App\DTO\Preference\Preference;
use PHPUnit\Framework\TestCase;

class PreferenceTest extends TestCase
{
    public function test_it_creates_preference()
    {
        $categories = [
            ['slug' => 'restaurant', 'name' => 'Restaurant'],
            ['slug' => 'museum', 'name' => 'Museum'],
        ];
        
        $user = [
            'budget' => 2,
            'pace' => 3,
            'interests' => ['history', 'food']
        ];
        
        $preference = new Preference(
            categories: $categories,
            user: $user
        );

        $this->assertSame($categories, $preference->categories);
        $this->assertSame($user, $preference->user);
    }

    public function test_it_creates_preference_with_empty_arrays()
    {
        $preference = new Preference(
            categories: [],
            user: []
        );

        $this->assertEmpty($preference->categories);
        $this->assertEmpty($preference->user);
    }
}
