<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiEnhancerService
{
    private ?string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.google.gemini.key');
        $this->model = config('services.google.gemini.model', 'gemini-1.5-flash');
    }

    public function enhancePlaces(array $places, array $preferences, string $tripContext, string $locale = 'pl'): array
    {
        if (empty($this->apiKey) || empty($places)) {
            return [];
        }

        $placesListText = "";
        foreach ($places as $p) {
            $id = $p['external_id'] ?? $p['name'];
            $name = $p['name'];
            $cat = $p['category'] ?? 'general';
            $placesListText .= "- ID: \"{$id}\", Name: \"{$name}\", Category: \"{$cat}\"\n";
        }

        $topPrefs = array_keys(array_filter($preferences, fn($v) => (float)$v >= 0.6));
        $prefString = implode(', ', $topPrefs);

        $prompt = <<<TEXT
You are a helpful travel assistant.
Context: {$tripContext}.
User loves: [{$prefString}].
Target Language: {$locale}.

Task: Write ONE persuasive sentence explaining why the user should visit each place, connecting it to their interests.
If the place doesn't strongly match specific interests, give a general positive recommendation.

Format strictly as JSON object where keys are IDs and values are strings.
Example:
{
  "PLACE_123": "Great spot for art lovers.",
  "PLACE_456": "Perfect for relaxing."
}

Places to review:
{$placesListText}
TEXT;

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(3)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'temperature' => 0.6,
                    ]
                ]);

            if ($response->failed()) {
                Log::warning('Gemini API Error: ' . $response->body());
                return [];
            }

            $data = $response->json();
            $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $json = json_decode($rawText, true);

            return is_array($json) ? $json : [];

        } catch (\Throwable $e) {
            Log::warning('Gemini Exception: ' . $e->getMessage());
            return [];
        }
    }
}
