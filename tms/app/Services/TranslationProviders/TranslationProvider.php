<?php

declare(strict_types=1);

namespace Tms\Services\TranslationProviders;

interface TranslationProvider
{
    /**
     * Translate text from source to target language.
     *
     * Der Kontext ist optional: DeepL und Google nehmen keinen entgegen, die
     * KI-Provider schreiben ihn in den Prompt. Ohne Angabe verhalten sich alle
     * Provider wie bisher.
     *
     * @throws \RuntimeException if translation fails
     */
    public function translate(
        string $text,
        string $sourceLang,
        string $targetLang,
        ?TermContext $context = null,
    ): TranslationResult;

    /**
     * Check if this provider supports the given language pair.
     */
    public function supports(string $sourceLang, string $targetLang): bool;

    /**
     * Return the provider name identifier.
     */
    public function name(): string;
}
