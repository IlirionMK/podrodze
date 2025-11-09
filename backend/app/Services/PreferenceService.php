<?php

namespace App\Services;

use App\DTO\Preference\Preference;
use App\Interfaces\PreferenceServiceInterface;
use App\Models\Category;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreferenceService implements PreferenceServiceInterface
{
    public function getPreferences(Request $request): Preference
    {
        $categories = Category::all(['id', 'slug', 'translations'])
            ->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->translations[app()->getLocale()] ?? $c->translations['en'] ?? $c->slug,
            ])
            ->toArray();

        $prefs = UserPreference::where('user_id', $request->user()->id)
            ->get()
            ->mapWithKeys(fn ($p) => [$p->category->slug => (int) $p->score])
            ->toArray();

        return new Preference($categories, $prefs);
    }

    public function updatePreferences(Request $request): array
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
        ]);

        $userId = $request->user()->id;
        $slugs = array_keys($data['preferences']);
        $categories = Category::whereIn('slug', $slugs)->pluck('id', 'slug');

        DB::transaction(function () use ($data, $categories, $userId) {
            foreach ($data['preferences'] as $slug => $score) {
                if (!isset($categories[$slug])) continue;
                $s = max(0, min(2, (int)$score));
                UserPreference::updateOrCreate(
                    ['user_id' => $userId, 'category_id' => $categories[$slug]],
                    ['score' => $s]
                );
            }
        });

        return ['status' => 'ok'];
    }
}
