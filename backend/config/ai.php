<?php

return [
    'suggestions' => [
        'enabled' => env('AI_SUGGESTIONS_ENABLED', true),

        'default_limit' => env('AI_SUGGESTIONS_DEFAULT_LIMIT', 8),
        'max_limit' => env('AI_SUGGESTIONS_MAX_LIMIT', 20),

        'default_radius_m' => env('AI_SUGGESTIONS_DEFAULT_RADIUS_M', 3000),
        'min_radius_m' => env('AI_SUGGESTIONS_MIN_RADIUS_M', 200),
        'max_radius_m' => env('AI_SUGGESTIONS_MAX_RADIUS_M', 20000),

        'cache_ttl_minutes' => env('AI_SUGGESTIONS_CACHE_TTL_MINUTES', 720),

        'quality' => [
            'strict' => env('AI_SUGGESTIONS_STRICT', true),
            'min_rating' => env('AI_SUGGESTIONS_MIN_RATING', 4.5),
            'min_reviews' => env('AI_SUGGESTIONS_MIN_REVIEWS', 250),
            'min_score' => env('AI_SUGGESTIONS_MIN_SCORE', 0.72),
        ],

        'external' => [
            'enabled' => env('AI_SUGGESTIONS_EXTERNAL_ENABLED', false),
            'max_candidates' => env('AI_SUGGESTIONS_EXTERNAL_MAX_CANDIDATES', 30),
        ],

        'itinerary_fill_enabled' => env('AI_ITINERARY_FILL_ENABLED', false),
    ],
];
