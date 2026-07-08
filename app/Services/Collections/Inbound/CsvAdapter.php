<?php

declare(strict_types=1);

namespace App\Services\Collections\Inbound;

use App\Services\Collections\DTO\OfferContext;

/**
 * Erwartete Spalten (Kopfzeile, Reihenfolge egal): sku, quantity, unit, note.
 * CSV traegt keine Empfaenger-Struktur -- Organisation kommt ueber $options['organization']
 * (z.B. aus einem Formularfeld neben dem Datei-Upload).
 */
class CsvAdapter implements InboundAdapterInterface
{
    public function parse(string $raw, array $options = []): OfferContext
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($raw))));
        if (empty($lines)) {
            return new OfferContext(organization: $options['organization'] ?? null, currency: $options['currency'] ?? null, items: []);
        }

        $header = array_map(fn (string $h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        $items = [];

        foreach ($lines as $line) {
            $row = array_combine($header, array_pad(str_getcsv($line), count($header), null));
            if ($row === false || empty($row['sku'])) {
                continue;
            }

            $items[] = [
                'sku_candidate' => trim((string) $row['sku']),
                'quantity' => (float) ($row['quantity'] ?? 1),
                'unit' => $row['unit'] ?? null,
                'note' => $row['note'] ?? null,
            ];
        }

        return new OfferContext(
            organization: $options['organization'] ?? null,
            currency: $options['currency'] ?? null,
            items: $items,
            reference: $options['reference'] ?? null,
        );
    }
}
