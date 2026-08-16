<?php

declare(strict_types=1);

namespace Tms\Services\TranslationProviders;

use Illuminate\Support\Facades\Http;

class GoogleTranslateProvider implements TranslationProvider
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('tms.providers.google.api_key', '');
    }

    public function translate(
        string $text,
        string $sourceLang,
        string $targetLang,
        ?TermContext $context = null,
    ): TranslationResult
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Google Translate API key not configured.');
        }

        $response = Http::timeout(15)
            ->post('https://translation.googleapis.com/language/translate/v2', [
                'q' => $text,
                'source' => substr($sourceLang, 0, 2),
                'target' => substr($targetLang, 0, 2),
                'format' => 'text',
                'key' => $this->apiKey,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Google Translate API error: {$response->status()}");
        }

        $translated = $response->json('data.translations.0.translatedText');
        if (empty($translated)) {
            throw new \RuntimeException('Google Translate returned empty result.');
        }

        // Google Cloud Translation cost: $20 per 1M chars = $0.00002 per char
        $cost = mb_strlen($text) * 0.00002;

        return new TranslationResult(
            text: $translated,
            provider: 'google',
            confidence: 0.85,
            cost: $cost,
        );
    }

    public function supports(string $sourceLang, string $targetLang): bool
    {
        // Google Translate supports 100+ languages
        return !empty($this->apiKey);
    }

    public function name(): string
    {
        return 'google';
    }
}
