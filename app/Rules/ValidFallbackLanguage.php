<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Language;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Prueft den Anzeige-Fallback einer Sprache.
 *
 * Verhindert die zwei Faelle, die die Anzeige im Produkteditor in eine
 * Endlosschleife schicken wuerden: Fallback auf sich selbst und Zyklen
 * ueber mehrere Sprachen (fi -> en -> fi).
 */
class ValidFallbackLanguage implements ValidationRule
{
    public function __construct(private readonly ?string $ownCode)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->ownCode !== null && $value === $this->ownCode) {
            $fail('Eine Sprache kann nicht auf sich selbst zurueckfallen.');
            return;
        }

        if (!Language::where('technical_name', $value)->exists()) {
            $fail("Die Sprache \"{$value}\" ist nicht angelegt.");
            return;
        }

        if ($this->ownCode === null) {
            return;
        }

        // Kette ab dem Ziel verfolgen: taucht die eigene Sprache darin auf,
        // entstuende ein Zyklus.
        $seen = [];
        $current = $value;

        while ($current !== null && $current !== '') {
            if ($current === $this->ownCode) {
                $fail('Das ergaebe einen Zyklus in der Fallback-Kette.');
                return;
            }

            if (isset($seen[$current])) {
                return; // bestehender Zyklus, nicht durch diese Aenderung verursacht
            }

            $seen[$current] = true;
            $current = Language::where('technical_name', $current)->value('fallback_language');
        }
    }
}
