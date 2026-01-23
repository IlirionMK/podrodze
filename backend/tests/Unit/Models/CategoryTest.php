<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Place;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\ModelTestCase;

class CategoryTest extends ModelTestCase
{
    #[Test]
    public function it_has_required_fields()
    {
        $translations = ['en' => 'Test Category'];
        $category = $this->createCategory([
            'slug' => 'test-category',
            'translations' => $translations,
        ]);

        $this->assertEquals('test-category', $category->slug);
        $this->assertEquals($translations, $category->translations);
        $this->assertEquals('Test Category', $category->name);
    }

    #[Test]
    public function it_has_include_in_preferences_default()
    {
        $category = $this->createCategory([
            'slug' => 'test-category',
            'translations' => ['en' => 'Test Category']
        ]);
        
        $this->assertTrue($category->include_in_preferences);
    }
}
