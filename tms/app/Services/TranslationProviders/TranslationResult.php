<?php

declare(strict_types=1);

namespace Tms\Services\TranslationProviders;

class TranslationResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly ?float $confidence = null,
        public readonly ?float $cost = null,
    ) {
    }
}
