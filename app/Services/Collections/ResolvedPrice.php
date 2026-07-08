<?php

declare(strict_types=1);

namespace App\Services\Collections;

/**
 * Ergebnis der Preisaufloesung fuer eine Collection-Position.
 */
readonly class ResolvedPrice
{
    public function __construct(
        public float $amount,
        public string $currency,
        public string $priceTypeId,
        /** True wenn kein aktuell gueltiger Preis gefunden wurde und der juengste abgelaufene genutzt wird. */
        public bool $isExpired = false,
    ) {}
}
