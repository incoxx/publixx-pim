<?php

declare(strict_types=1);

namespace Tms\Services\TranslationProviders;

/**
 * Was ueber einen Begriff bekannt ist, wenn er uebersetzt wird.
 *
 * Benennungen im PIM sind ein bis drei Woerter ohne Satzkontext — genau dort
 * raet maschinelle Uebersetzung ("Anschluss" vs. "anschliessen", "Blatt" als
 * Saegeblatt oder Papierblatt). Diese Angaben gehen deshalb in den Prompt der
 * KI-Provider ein. DeepL und Google nehmen keinen Kontext entgegen und
 * ignorieren das Objekt.
 */
final class TermContext
{
    /**
     * @param  string|null  $definition  Bedeutung in der Quellsprache
     * @param  string|null  $wordClass   noun, verb, adjective, ...
     * @param  string|null  $domain      Bereich, z.B. attribute, value_list_entry
     * @param  string[]     $usedIn      Beispiele, wo der Begriff vorkommt
     */
    public function __construct(
        public readonly ?string $definition = null,
        public readonly ?string $wordClass = null,
        public readonly ?string $domain = null,
        public readonly array $usedIn = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->definition === null
            && $this->wordClass === null
            && $this->domain === null
            && $this->usedIn === [];
    }

    /**
     * Kontext als Prompt-Abschnitt. Leer, wenn nichts bekannt ist — dann soll
     * der Prompt auch nicht mit leeren Feldern aufgeblaeht werden.
     */
    public function toPromptSection(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $lines = [];

        if ($this->definition !== null) {
            $lines[] = "Meaning: {$this->definition}";
        }
        if ($this->wordClass !== null) {
            $lines[] = 'Part of speech: ' . str_replace('_', ' ', $this->wordClass);
        }
        if ($this->domain !== null) {
            $lines[] = "Field: {$this->domain}";
        }
        if ($this->usedIn !== []) {
            $lines[] = 'Used for: ' . implode(', ', array_slice($this->usedIn, 0, 5));
        }

        return "\n\nContext:\n" . implode("\n", $lines);
    }
}
