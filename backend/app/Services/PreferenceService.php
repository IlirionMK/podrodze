<?php

namespace App\Services;

use App\DTO\Preference\Preference;
use App\Interfaces\PreferenceServiceInterface;
use App\Models\Category;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;

class PreferenceService implements PreferenceServiceInterface
{
    /**
     * Get available categories and user's current preferences.
     *
     * @param User $user
     * @return Preference
     */
    public function getPreferences(User $user): Preference
    {
        // Fetch all categories with localized names
        $categories = Category::all(['id', 'slug', 'translations'])
            ->map(fn($c) => [
                'slug' => $c->slug,
                'name' => $c->translations[app()->getLocale()] ?? $c->translations['en'] ?? $c->slug,
            ])
            ->toArray();

        // Fetch user-specific preferences (score 0–2)
        $prefs = UserPreference::where('user_id', $user->id)
            ->with('category:id,slug')
            ->get()
            ->mapWithKeys(fn($p) => [$p->category->slug => (int) $p->score])
            ->toArray();

        return new Preference($categories, $prefs);
    }

    /**
     * Update user's preference scores for available categories.
     *
     * @param User $user
     * @param array<string,int> $preferences Example: ['museum' => 2, 'food' => 1]
     * @return array{status: string}
     */
    public function updatePreferences(User $user, array $preferences): array
    {
        $slugs = array_keys($preferences);
        $categories = Category::whereIn('slug', $slugs)->pluck('id', 'slug');

        DB::transaction(function () use ($preferences, $categories, $user) {
            foreach ($preferences as $slug => $score) {
                if (!isset($categories[$slug])) {
                    continue;
                }

                $validatedScore = max(0, min(2, (int) $score));

                UserPreference::updateOrCreate(
                    ['user_id' => $user->id, 'category_id' => $categories[$slug]],
                    ['score' => $validatedScore]
                );
            }
        });

        return ['status' => 'ok'];
    }
}
