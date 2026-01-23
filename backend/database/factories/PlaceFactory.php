<?php

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'google_place_id' => $this->faker->unique()->uuid(),
            'category_slug' => $this->faker->randomElement(['food', 'museum', 'nature', 'attraction', 'other']),
            'rating' => $this->faker->randomFloat(1, 0, 5),
            'meta' => [
                'user_ratings_total' => $this->faker->numberBetween(0, 5000),
                'source' => 'google',
                'google_types' => [$this->faker->randomElement(['restaurant', 'museum', 'park'])]
            ],
            'opening_hours' => [
                'monday' => '9:00-22:00',
                'tuesday' => '9:00-22:00',
            ],
            'location' => null, // Will be set in tests
        ];
    }
}
