<?php

declare(strict_types=1);

namespace Tms\Services\TranslationProviders;

use Illuminate\Support\Facades\Http;

class OpenAIProvider implements TranslationProvider
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('tms.providers.openai.api_key', '');
        $this->model = config('tms.providers.openai.model', 'gpt-4o');
    }

    public function translate(string $text, string $sourceLang, string $targetLang): TranslationResult
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured.');
        }

        $langNames = $this->languageNames();
        $sourceName = $langNames[$sourceLang] ?? $sourceLang;
        $targetName = $langNames[$targetLang] ?? $targetLang;

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'max_tokens' => 1024,
                'temperature' => 0.1,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator for PIM (Product Information Management) metadata. '
                            . 'Translate the given text accurately. Return ONLY the translated text, nothing else.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Translate from {$sourceName} to {$targetName}:\n\n{$text}",
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("OpenAI API error: {$response->status()} — {$response->body()}");
        }

        $translated = trim($response->json('choices.0.message.content') ?? '');
        if (empty($translated)) {
            throw new \RuntimeException('OpenAI returned empty translation.');
        }

        // GPT-4o pricing: ~$2.50/M input, ~$10/M output tokens
        $inputTokens = $response->json('usage.prompt_tokens', 0);
        $outputTokens = $response->json('usage.completion_tokens', 0);
        $cost = ($inputTokens * 0.0000025) + ($outputTokens * 0.00001);

        return new TranslationResult(
            text: $translated,
            provider: 'openai',
            confidence: 0.90,
            cost: $cost,
        );
    }

    public function supports(string $sourceLang, string $targetLang): bool
    {
        return !empty($this->apiKey);
    }

    public function name(): string
    {
        return 'openai';
    }

    private function languageNames(): array
    {
        return [
            'de' => 'German',
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'pt' => 'Portuguese',
            'cs' => 'Czech',
            'da' => 'Danish',
            'sv' => 'Swedish',
            'nb' => 'Norwegian',
            'fi' => 'Finnish',
            'hu' => 'Hungarian',
            'ro' => 'Romanian',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'bg' => 'Bulgarian',
            'el' => 'Greek',
            'tr' => 'Turkish',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ru' => 'Russian',
        ];
    }
}
