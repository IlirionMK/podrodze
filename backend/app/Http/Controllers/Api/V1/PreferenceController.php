<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreferenceController extends Controller
{
    /**
     * @group Preferences
     *
     * Get available categories and user's current preferences.
     *
     * @response 200 scenario="Example"
     * {
     *   "data": [
     *     {"slug": "museum", "name": "Muzea"},
     *     {"slug": "food", "name": "Jedzenie"}
     *   ],
     *   "user": {
     *     "museum": 2,
     *     "food": 0,
     *     "nature": 1
     *   }
     * }
     */
    public function index(Request $request)
    {
        $categories = Category::all(['id', 'slug', 'translations'])
            ->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->translations[app()->getLocale()] ?? $c->translations['en'] ?? $c->slug,
            ]);

        $prefs = UserPreference::where('user_id', $request->user()->id)
            ->get()
            ->mapWithKeys(fn ($p) => [$p->category->slug => (int) $p->score]);

        return response()->json([
            'data' => $categories,
            'user' => $prefs,
        ]);
    }

    /**
     * @group Preferences
     *
     * Save or update user's preferences.
     *
     * @bodyParam preferences object required Example: {"museum":2,"food":1,"nature":0}
     * @response 200 {"status": "ok"}
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
        ]);

        $userId = $request->user()->id;
        $slugs = array_keys($data['preferences']);

        $categories = Category::whereIn('slug', $slugs)->pluck('id', 'slug');

        DB::transaction(function () use ($data, $categories, $userId) {
            foreach ($data['preferences'] as $slug => $score) {
                if (!isset($categories[$slug])) {
                    continue;
                }

                $s = max(0, min(2, (int)$score));

                UserPreference::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'category_id' => $categories[$slug],
                    ],
                    [
                        'score' => $s,
                    ]
                );
            }
        });

        return response()->json(['status' => 'ok']);
    }
}
