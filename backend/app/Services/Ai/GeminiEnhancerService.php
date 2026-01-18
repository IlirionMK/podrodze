<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiEnhancerService
{
    public function enhancePlaces(array $places, array $preferences, string $tripContext, string $locale = 'en'): array
    {
        $apiKey = (string) config('services.google.gemini.key');
        if ($apiKey === '' || empty($places)) {
            return [];
        }

        $list = collect($places)
            ->map(static fn ($p) => "- ID: {$p['external_id']}, Name: {$p['name']}, Category: {$p['category']}")
            ->implode("\n");

        $topPrefs = collect($preferences)
            ->filter(static fn ($v) => (float) $v > 0.5)
            ->keys()
            ->implode(', ');

        $prompt = <<<TEXT
You are a charismatic travel guide for: {$tripContext}.
User likes: {$topPrefs}.
Task: Write 1 very short, emotional recommendation (max 10 words) for each ID in {$locale}.
Return ONLY valid JSON as an object where keys are IDs (without prefixes like "google:") and values are strings.
Places:
{$list}
TEXT;

        try {
            $response = Http::timeout(15)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['response_mime_type' => 'application/json'],
                ])
                ->throw();

            $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

            $decoded = json_decode($text, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            Log::error('Gemini failed: ' . $e->getMessage());
            return [];
        }
    }
}
