<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'score' => $this->faker->numberBetween(0, 2),
        ];
    }
}
