<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $slug = $this->faker->randomElement(['food', 'museum', 'nature', 'attraction', 'other']);
        
        return [
            'slug' => $slug,
            'translations' => [
                'en' => ucfirst($slug),
                'pl' => ucfirst($slug) . ' (PL)',
            ],
            'include_in_preferences' => true,
        ];
    }
}
