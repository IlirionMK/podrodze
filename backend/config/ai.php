<?php

return [
    'suggestions' => [
        'enabled' => env('AI_SUGGESTIONS_ENABLED', true),

        'default_limit' => env('AI_SUGGESTIONS_DEFAULT_LIMIT', 8),
        'max_limit' => env('AI_SUGGESTIONS_MAX_LIMIT', 20),

        // Увеличим радиус поиска по умолчанию до 10 км,
        // чтобы точно достать "Станцию" (5.5 км) с первого раза.
        'default_radius_m' => env('AI_SUGGESTIONS_DEFAULT_RADIUS_M', 10000),
        'min_radius_m' => env('AI_SUGGESTIONS_MIN_RADIUS_M', 200),
        'max_radius_m' => env('AI_SUGGESTIONS_MAX_RADIUS_M', 50000), // Расширим макс до 50 км

        'cache_ttl_minutes' => env('AI_SUGGESTIONS_CACHE_TTL_MINUTES', 720),

        'quality' => [
            'strict' => env('AI_SUGGESTIONS_STRICT', false), // Отключаем строгость

            // Ставим низкие пороги, чтобы видеть ВСЕ результаты из базы
            'min_rating' => env('AI_SUGGESTIONS_MIN_RATING', 3.0), // Было 4.5 (слишком много отсеивало)
            'min_reviews' => env('AI_SUGGESTIONS_MIN_REVIEWS', 0),   // Было 250 (отсеивало новые места)

            // САМОЕ ВАЖНОЕ: Снижаем порог прохождения фильтра
            // Если поставить 0.1, система покажет всё, что нашла, даже если не уверена.
            'min_score' => env('AI_SUGGESTIONS_MIN_SCORE', 0.10),   // Было 0.72
        ],

        'external' => [
            'enabled' => env('AI_SUGGESTIONS_EXTERNAL_ENABLED', true),
            'max_candidates' => env('AI_SUGGESTIONS_EXTERNAL_MAX_CANDIDATES', 10), // Можно чуть больше для теста
        ],

        'itinerary_fill_enabled' => env('AI_ITINERARY_FILL_ENABLED', false),
    ],
];
