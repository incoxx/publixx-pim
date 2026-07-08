<?php

declare(strict_types=1);

namespace App\Services\Collections\DTO;

/**
 * Normalisiertes Ergebnis eines Inbound-Adapters (RFQ/JSON/CSV) -- kein direktes
 * DB-Schreiben im Adapter, CollectionFactory materialisiert daraus collection +
 * collection_items.
 */
readonly class OfferContext
{
    /**
     * @param  array{external_ref?: ?string, name?: ?string, language?: ?string, address_block?: ?string, price_list_ref?: ?string, currency?: ?string}|null  $organization
     * @param  array<int, array{external_product_id?: ?string, sku_candidate?: ?string, quantity: float, unit?: ?string, position?: ?int, note?: ?string, meta?: array}>  $items
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ?array $organization,
        public ?string $currency,
        public array $items,
        public ?string $reference = null,
        public ?string $validUntil = null,
        public array $meta = [],
    ) {}
}
