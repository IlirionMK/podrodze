<?php

namespace App\Services;

use App\DTO\Preference\Preference;
use App\Interfaces\PreferenceServiceInterface;
use App\Models\Category;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;

class PreferenceService implements PreferenceServiceInterface
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {}

    public function getPreferences(User $user): Preference
    {
        $categories = Category::all(['id', 'slug', 'translations'])
            ->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->translations[app()->getLocale()] ?? $c->translations['en'] ?? $c->slug,
            ])
            ->toArray();

        $prefs = UserPreference::where('user_id', $user->id)
            ->with('category:id,slug')
            ->get()
            ->mapWithKeys(fn ($p) => [$p->category->slug => (int) $p->score])
            ->toArray();

        return new Preference($categories, $prefs);
    }

    public function updatePreferences(User $user, array $preferences): Preference
    {
        $input = [];
        foreach ($preferences as $slug => $score) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }

            $input[$slug] = max(0, min(2, (int) $score));
        }

        $slugs = array_keys($input);
        if ($slugs === []) {
            return $this->getPreferences($user);
        }

        $categories = Category::whereIn('slug', $slugs)->pluck('id', 'slug');

        $existing = UserPreference::query()
            ->where('user_id', $user->id)
            ->whereIn('category_id', $categories->values()->all())
            ->pluck('score', 'category_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $changes = [];
        $ignored = [];

        foreach ($input as $slug => $score) {
            if (!isset($categories[$slug])) {
                $ignored[] = $slug;
                continue;
            }

            $categoryId = (int) $categories[$slug];
            $before = $existing[$categoryId] ?? null;

            if ($before !== $score) {
                $changes[$slug] = [
                    'before' => $before,
                    'after' => $score,
                ];
            }
        }

        DB::transaction(function () use ($input, $categories, $user) {
            foreach ($input as $slug => $score) {
                if (!isset($categories[$slug])) {
                    continue;
                }

                UserPreference::updateOrCreate(
                    ['user_id' => $user->id, 'category_id' => $categories[$slug]],
                    ['score' => $score]
                );
            }
        });

        if ($changes !== [] || $ignored !== []) {
            $this->activityLogger->add(
                actor: $user,
                action: 'user.preferences_updated',
                target: $user,
                details: [
                    'user_id' => $user->getKey(),
                    'changes' => $changes,
                    'ignored' => $ignored,
                ]
            );
        }

        return $this->getPreferences($user);
    }
}
