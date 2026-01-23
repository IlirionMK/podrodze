<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\GeminiEnhancerService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeminiEnhancerServiceTest extends TestCase
{
    private GeminiEnhancerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeminiEnhancerService();
    }

    #[Test]
    public function it_returns_empty_array_when_api_key_is_missing(): void
    {
        Config::set('services.google.gemini.key', '');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food']
        ];

        $result = $this->service->enhancePlaces($places, ['food' => 1.0], 'Warsaw trip', 'en');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_places_array_is_empty(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $result = $this->service->enhancePlaces([], ['food' => 1.0], 'Warsaw trip', 'en');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_calls_gemini_api_with_correct_parameters(): void
    {
        Config::set('services.google.gemini.key', 'test-api-key');

        $places = [
            [
                'external_id' => 'google:123',
                'name' => 'Amazing Restaurant',
                'category' => 'food'
            ],
            [
                'external_id' => 'google:456',
                'name' => 'Cool Museum',
                'category' => 'museum'
            ]
        ];

        $preferences = ['food' => 2.0, 'museum' => 1.5];
        $tripContext = 'Weekend in Warsaw';
        $locale = 'en';

        $mockResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => '{"123": "Perfect for foodies!", "456": "Art lovers paradise"}'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($mockResponse, 200)
        ]);

        $result = $this->service->enhancePlaces($places, $preferences, $tripContext, $locale);

        Http::assertSent(function ($request) use ($tripContext, $preferences, $locale) {
            $body = $request->data();
            $prompt = $body['contents'][0]['parts'][0]['text'];

            // Check if prompt contains expected elements
            return str_contains($prompt, $tripContext) &&
                   str_contains($prompt, 'food') &&
                   str_contains($prompt, 'museum') && // Both preferences should be included as they are > 0.5
                   str_contains($prompt, 'Amazing Restaurant') &&
                   str_contains($prompt, 'Cool Museum') &&
                   str_contains($prompt, $locale) &&
                   str_contains($prompt, 'max 10 words');
        });

        $this->assertEquals([
            '123' => 'Perfect for foodies!',
            '456' => 'Art lovers paradise'
        ], $result);
    }

    #[Test]
    public function it_filters_preferences_by_threshold(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food']
        ];

        $preferences = ['food' => 0.8, 'museum' => 0.3, 'nature' => 0.6]; // Only food and nature > 0.5

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"123": "Great food spot"}']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->enhancePlaces($places, $preferences, 'Trip', 'en');

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'];
            return str_contains($prompt, 'food') &&
                   !str_contains($prompt, 'museum') &&
                   str_contains($prompt, 'nature'); // nature should be included as it's > 0.5
        });
    }

    #[Test]
    public function it_handles_api_errors_gracefully(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food']
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 500,
                    'message' => 'Internal server error',
                    'status' => 'INTERNAL'
                ]
            ], 500)
        ]);

        $result = $this->service->enhancePlaces($places, ['food' => 1.0], 'Trip', 'en');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_network_timeout(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food']
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException("cURL error 28: Operation timed out");
            }
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Gemini failed: cURL error 28: Operation timed out');

        $result = $this->service->enhancePlaces($places, ['food' => 1.0], 'Trip', 'en');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_malformed_json_response(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food']
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'invalid json response']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->enhancePlaces($places, ['food' => 1.0], 'Trip', 'en');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_missing_response_structure(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food']
        ];

        // Response missing candidates structure
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['data' => 'something'], 200)
        ]);

        $result = $this->service->enhancePlaces($places, ['food' => 1.0], 'Trip', 'en');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_removes_google_prefix_from_place_ids(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food'],
            ['external_id' => 'internal:456', 'name' => 'Museum', 'category' => 'museum']
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"123": "Great food!", "456": "Nice museum"}']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->enhancePlaces($places, ['food' => 1.0, 'museum' => 1.0], 'Trip', 'en');

        $this->assertEquals([
            '123' => 'Great food!',
            '456' => 'Nice museum'
        ], $result);
    }

    #[Test]
    public function it_formats_prompt_correctly_for_different_locales(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restauracja', 'category' => 'food']
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"123": "Świetne jedzenie!"}']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->enhancePlaces($places, ['food' => 1.0], 'Weekend w Warszawie', 'pl');

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'];
            return str_contains($prompt, 'Weekend w Warszawie') &&
                   str_contains($prompt, 'pl') &&
                   str_contains($prompt, 'Restauracja');
        });
    }

    #[Test]
    public function it_handles_empty_preferences_array(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food']
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"123": "Nice place"}']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->enhancePlaces($places, [], 'Trip', 'en');

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'];
            return str_contains($prompt, 'User likes: '); // Should be empty
        });

        $this->assertEquals(['123' => 'Nice place'], $result);
    }

    #[Test]
    public function it_sets_correct_request_headers_and_mime_type(): void
    {
        Config::set('services.google.gemini.key', 'test-key');

        $places = [
            ['external_id' => 'google:123', 'name' => 'Restaurant', 'category' => 'food']
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"123": "Good food"}']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->enhancePlaces($places, ['food' => 1.0], 'Trip', 'en');

        Http::assertSent(function ($request) {
            $body = $request->data();
            return isset($body['generationConfig']['response_mime_type']) &&
                   $body['generationConfig']['response_mime_type'] === 'application/json' &&
                   $request->hasHeader('Content-Type');
        });
    }
}
