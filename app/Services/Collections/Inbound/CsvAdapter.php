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
            // array_combine() verlangt gleich lange Arrays (wirft sonst seit PHP 8 einen
            // ValueError statt false zurueckzugeben) -- eine Zeile mit mehr Feldern als die
            // Kopfzeile (z.B. ein unescapetes Komma) wird auf die Kopfzeilen-Laenge gekappt
            // statt die gesamte Import-Anfrage abzubrechen; fehlende Felder werden aufgefuellt.
            $parsedRow = str_getcsv($line);
            $parsedRow = array_pad(array_slice($parsedRow, 0, count($header)), count($header), null);
            $row = array_combine($header, $parsedRow);

            // trim()/=== '' statt empty(): eine legitime SKU "0" darf nicht als leer gelten.
            $sku = $row['sku'] ?? null;
            if ($sku === null || trim((string) $sku) === '') {
                continue;
            }

            $items[] = [
                'sku_candidate' => trim((string) $sku),
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
