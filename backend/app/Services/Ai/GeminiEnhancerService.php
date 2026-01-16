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

    public function enhancePlaces(array $places, array $preferences, string $tripContext, string $locale = 'en'): array
    {
        if (empty($this->apiKey) || empty($places)) return [];

        $placesListText = "";
        foreach ($places as $p) {
            // Очищаем ID для ИИ, чтобы он вернул их в простом виде
            $id = strtolower(trim(str_replace(['google:', 'internal:'], '', (string)($p['external_id'] ?? $p['id']))));
            $placesListText .= "- ID: \"{$id}\", Name: \"{$p['name']}\", Category: \"{$p['category']}\"\n";
        }

        $languageName = ($locale === 'pl') ? 'Polish' : 'English';
        $topPrefs = array_keys(array_filter($preferences, fn($v) => (float)$v >= 0.5));
        $prefString = implode(', ', $topPrefs);

        $prompt = <<<TEXT
You are a charismatic local travel guide.
Task: Write ONE short, emotional recommendation (max 12 words) for each place in {$languageName}.
IMPORTANT: DO NOT start with "Recommended because..." or "Based on your interests...".
Be creative!
Example: "Perfect spot for your morning coffee with a stunning view!" or "Since you love history, this museum is a hidden gem you can't miss."
Return ONLY a valid JSON object where keys are the IDs provided.
TEXT;

        try {
            $response = Http::timeout(15)->post("{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.8
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API Error: ' . $response->body());
                return [];
            }

            $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Надежный парсинг JSON из ответа
            if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $rawText, $matches)) {
                return json_decode($matches[0], true) ?? [];
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('Gemini Exception: ' . $e->getMessage());
            return [];
        }
    }
}
