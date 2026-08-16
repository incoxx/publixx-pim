<?php

declare(strict_types=1);

namespace Tms\Services\TranslationProviders;

use Illuminate\Support\Facades\Log;

class ProviderChain
{
    /** @var TranslationProvider[] */
    private array $providers = [];

    public function __construct()
    {
        $chain = config('tms.provider_chain', ['deepl', 'google']);

        $providerMap = [
            'deepl' => DeepLProvider::class,
            'google' => GoogleTranslateProvider::class,
            'claude' => ClaudeProvider::class,
            'openai' => OpenAIProvider::class,
        ];

        foreach ($chain as $name) {
            $name = trim($name);
            if (isset($providerMap[$name])) {
                $this->providers[] = new $providerMap[$name]();
            }
        }
    }

    /**
     * Translate text using the first available provider.
     * Falls back to the next provider on error.
     */
    public function translate(
        string $text,
        string $sourceLang,
        string $targetLang,
        ?TermContext $context = null,
    ): ?TranslationResult
    {
        foreach ($this->providers as $provider) {
            if (!$provider->supports($sourceLang, $targetLang)) {
                continue;
            }

            try {
                return $provider->translate($text, $sourceLang, $targetLang, $context);
            } catch (\Throwable $e) {
                Log::warning("MT provider '{$provider->name()}' failed: {$e->getMessage()}");
                continue;
            }
        }

        return null;
    }

    /**
     * Get list of configured providers.
     *
     * @return string[]
     */
    public function availableProviders(): array
    {
        return array_map(fn ($p) => $p->name(), $this->providers);
    }
}
