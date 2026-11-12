<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
    }

    public function generate(string $prompt, array $fallback = []): array
    {
        if (empty($this->apiKey)) {
            return $fallback ?: $this->staticFallback($prompt);
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text') ?? $this->staticFallback($prompt);
            }
        } catch (\Throwable $e) {
            Log::warning('GeminiService failed: ' . $e->getMessage());
        }

        return $fallback ?: $this->staticFallback($prompt);
    }

    private function staticFallback(string $prompt): array
    {
        // shortcut: static fallback only, upgrade when Gemini quota/key is set
        return ['text' => 'Konten tidak tersedia saat ini. Coba lagi nanti.'];
    }
}
