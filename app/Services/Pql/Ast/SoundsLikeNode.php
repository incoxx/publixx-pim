<?php

declare(strict_types=1);

namespace App\Services\Pql\Ast;

/**
 * Phonetic matching: field SOUNDS_LIKE 'text'
 *
 * Strategy: Kölner Phonetik (primary, DE) + Soundex (fallback, EN)
 */
final class SoundsLikeNode
{
    /**
     * @param string $field Field to match against
     * @param string $term Phonetic search term
     * @param bool $negated NOT SOUNDS_LIKE
     */
    public function __construct(
        public readonly string $field,
        public readonly string $term,
        public readonly bool $negated = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'SOUNDS_LIKE',
            'field' => $this->field,
            'term' => $this->term,
            'negated' => $this->negated,
        ];
    }
}
