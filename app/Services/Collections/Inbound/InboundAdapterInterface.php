<?php

declare(strict_types=1);

namespace App\Services\Collections\Inbound;

use App\Services\Collections\DTO\OfferContext;

interface InboundAdapterInterface
{
    /**
     * @param  array<string, mixed>  $options  Adapter-spezifische Zusatzangaben (z.B. Organisation
     *         fuer CSV, die keine eigene Empfaenger-Struktur mitbringt).
     */
    public function parse(string $raw, array $options = []): OfferContext;
}
