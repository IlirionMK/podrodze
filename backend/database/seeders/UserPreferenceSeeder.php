<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\UserPreference;

class UserPreferenceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Run CategorySeeder first.');
            return;
        }

        $users = User::all();

        foreach ($users as $user) {
            foreach ($categories as $category) {
                UserPreference::updateOrCreate(
                    [
                        'user_id'     => $user->id,
                        'category_id' => $category->id,
                    ],
                    [
                        'score' => rand(0, 2),
                    ]
                );
            }
        }

        $this->command->info('User preferences seeded successfully.');
    }
}
