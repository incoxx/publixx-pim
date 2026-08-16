<?php

declare(strict_types=1);

namespace Tms\Services\TranslationProviders;

use Illuminate\Support\Facades\Http;

class DeepLProvider implements TranslationProvider
{
    private const array SUPPORTED_LANGUAGES = [
        'bg', 'cs', 'da', 'de', 'el', 'en', 'es', 'et', 'fi', 'fr',
        'hu', 'id', 'it', 'ja', 'ko', 'lt', 'lv', 'nb', 'nl', 'pl',
        'pt', 'ro', 'ru', 'sk', 'sl', 'sv', 'tr', 'uk', 'zh',
    ];

    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('tms.providers.deepl.api_key', '');
        $this->apiUrl = rtrim(config('tms.providers.deepl.api_url', 'https://api-free.deepl.com/v2'), '/');
    }

    public function translate(
        string $text,
        string $sourceLang,
        string $targetLang,
        ?TermContext $context = null,
    ): TranslationResult
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('DeepL API key not configured.');
        }

        // DeepL uses uppercase language codes, with special handling for EN/PT variants
        $sourceCode = $this->toDeepLCode($sourceLang);
        $targetCode = $this->toDeepLTargetCode($targetLang);

        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => "DeepL-Auth-Key {$this->apiKey}"])
            ->post("{$this->apiUrl}/translate", [
                'text' => [$text],
                'source_lang' => $sourceCode,
                'target_lang' => $targetCode,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("DeepL API error: {$response->status()} — {$response->body()}");
        }

        $translated = $response->json('translations.0.text');
        if (empty($translated)) {
            throw new \RuntimeException('DeepL returned empty translation.');
        }

        // DeepL cost: roughly €0.00002 per character (€20 per 1M chars)
        $cost = mb_strlen($text) * 0.00002;

        return new TranslationResult(
            text: $translated,
            provider: 'deepl',
            confidence: 0.95,
            cost: $cost,
        );
    }

    public function supports(string $sourceLang, string $targetLang): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        $source = strtolower(substr($sourceLang, 0, 2));
        $target = strtolower(substr($targetLang, 0, 2));

        return in_array($source, self::SUPPORTED_LANGUAGES) && in_array($target, self::SUPPORTED_LANGUAGES);
    }

    public function name(): string
    {
        return 'deepl';
    }

    private function toDeepLCode(string $lang): string
    {
        return strtoupper(substr($lang, 0, 2));
    }

    private function toDeepLTargetCode(string $lang): string
    {
        $code = strtoupper(substr($lang, 0, 2));

        // DeepL requires specific target variants
        return match ($code) {
            'EN' => 'EN-US',
            'PT' => 'PT-PT',
            default => $code,
        };
    }
}
