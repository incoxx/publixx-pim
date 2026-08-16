<?php

declare(strict_types=1);

namespace Tms\Services\TranslationProviders;

use Illuminate\Support\Facades\Http;

class ClaudeProvider implements TranslationProvider
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('tms.providers.claude.api_key', '');
        $this->model = config('tms.providers.claude.model', 'claude-sonnet-4-6');
    }

    public function translate(
        string $text,
        string $sourceLang,
        string $targetLang,
        ?TermContext $context = null,
    ): TranslationResult
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Anthropic API key not configured.');
        }

        $langNames = $this->languageNames();
        $sourceName = $langNames[$sourceLang] ?? $sourceLang;
        $targetName = $langNames[$targetLang] ?? $targetLang;

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Translate the following text from {$sourceName} to {$targetName}. "
                            . "This is a PIM (Product Information Management) metadata term — "
                            . "a short label without sentence context, so use the context below "
                            . "when deciding between possible readings. "
                            . "Return ONLY the translated text, nothing else.\n\n"
                            . "Text: {$text}"
                            . ($context?->toPromptSection() ?? ''),
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Anthropic API error: {$response->status()}");
        }

        $translated = trim($response->json('content.0.text') ?? '');
        if (empty($translated)) {
            throw new \RuntimeException('Claude returned empty translation.');
        }

        // Anthropic pricing: ~$3/M input tokens, ~$15/M output tokens (Sonnet)
        $inputTokens = $response->json('usage.input_tokens', 0);
        $outputTokens = $response->json('usage.output_tokens', 0);
        $cost = ($inputTokens * 0.000003) + ($outputTokens * 0.000015);

        return new TranslationResult(
            text: $translated,
            provider: 'claude',
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
        return 'claude';
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
