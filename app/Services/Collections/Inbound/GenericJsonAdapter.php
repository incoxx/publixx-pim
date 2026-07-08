<?php

declare(strict_types=1);

namespace App\Services\Collections\Inbound;

use App\Services\Collections\DTO\OfferContext;

/**
 * Erwartetes Format (bereits nahe an OfferContext, kaum Transformation noetig):
 *
 * {
 *   "organization": {"external_ref": "...", "name": "...", "language": "de", "price_list_ref": "..."},
 *   "currency": "EUR",
 *   "reference": "RFQ-2026-042",
 *   "items": [
 *     {"external_product_id": "...", "sku_candidate": "EW-ABS-001", "quantity": 10, "unit": "Stk", "note": "..."}
 *   ]
 * }
 */
class GenericJsonAdapter implements InboundAdapterInterface
{
    public function parse(string $raw, array $options = []): OfferContext
    {
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $items = [];
        foreach ((array) ($data['items'] ?? []) as $rawItem) {
            $items[] = [
                'external_product_id' => $rawItem['external_product_id'] ?? null,
                'sku_candidate' => $rawItem['sku_candidate'] ?? $rawItem['sku'] ?? null,
                'quantity' => (float) ($rawItem['quantity'] ?? 1),
                'unit' => $rawItem['unit'] ?? null,
                'note' => $rawItem['note'] ?? null,
                'meta' => $rawItem['meta'] ?? [],
            ];
        }

        return new OfferContext(
            organization: $data['organization'] ?? $options['organization'] ?? null,
            currency: $data['currency'] ?? null,
            items: $items,
            reference: $data['reference'] ?? null,
            validUntil: $data['valid_until'] ?? null,
            meta: $data['meta'] ?? [],
        );
    }
}
