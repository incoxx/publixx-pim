<?php

declare(strict_types=1);

namespace App\Services\Export\Writers;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class CsvWriter
{
    public function write(array $data, string $fileName): StreamedResponse
    {
        $products = $data['products'] ?? [];

        $hasAttributes = collect($products)->contains(fn($p) => !empty($p['attributes']));
        $hasPrices = collect($products)->contains(fn($p) => !empty($p['prices']));
        $hasRelations = collect($products)->contains(fn($p) => !empty($p['relations']));
        $hasMedia = collect($products)->contains(fn($p) => !empty($p['media']));

        $multipleSheets = ($hasAttributes || $hasPrices || $hasRelations || $hasMedia);

        if (!$multipleSheets) {
            return $this->writeSingleCsv($products, $fileName);
        }

        return $this->writeZippedCsvs($data, $fileName);
    }

    private function writeSingleCsv(array $products, string $fileName): StreamedResponse
    {
        $fullName = $fileName . '.csv';

        return new StreamedResponse(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, ['SKU', 'Name', 'Status', 'EAN', 'Produkttyp'], ';');
            foreach ($products as $product) {
                fputcsv($handle, [
                    $product['sku'] ?? '',
                    $product['name'] ?? '',
                    $product['status'] ?? '',
                    $product['ean'] ?? '',
                    $product['product_type'] ?? '',
                ], ';');
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$fullName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    private function writeZippedCsvs(array $data, string $fileName): StreamedResponse
    {
        $products = $data['products'] ?? [];
        $fullName = $fileName . '.zip';
        $tmpFile = tempnam(sys_get_temp_dir(), 'export-csv-') . '.zip';

        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE);

        // Produkte CSV
        $zip->addFromString('produkte.csv', $this->buildCsv(
            ['SKU', 'Name', 'Status', 'EAN', 'Produkttyp'],
            array_map(fn($p) => [$p['sku'] ?? '', $p['name'] ?? '', $p['status'] ?? '', $p['ean'] ?? '', $p['product_type'] ?? ''], $products),
        ));

        // Attributwerte CSV
        $attrRows = [];
        foreach ($products as $p) {
            foreach ($p['attributes'] ?? [] as $a) {
                $attrRows[] = [$p['sku'], $a['attribute'], $a['attribute_name'], $a['value'], $a['language']];
            }
        }
        if (!empty($attrRows)) {
            $zip->addFromString('attributwerte.csv', $this->buildCsv(
                ['SKU', 'Attribut', 'Attributname', 'Wert', 'Sprache'],
                $attrRows,
            ));
        }

        // Preise CSV
        $priceRows = [];
        foreach ($products as $p) {
            foreach ($p['prices'] ?? [] as $pr) {
                $priceRows[] = [$p['sku'], $pr['price_type'], $pr['amount'], $pr['currency'], $pr['scale_from'], $pr['valid_from'], $pr['valid_to']];
            }
        }
        if (!empty($priceRows)) {
            $zip->addFromString('preise.csv', $this->buildCsv(
                ['SKU', 'Preistyp', 'Betrag', 'Währung', 'Ab Menge', 'Gültig von', 'Gültig bis'],
                $priceRows,
            ));
        }

        // Beziehungen CSV
        $relRows = [];
        foreach ($products as $p) {
            foreach ($p['relations'] ?? [] as $r) {
                $relRows[] = [$p['sku'], $r['target_sku'], $r['relation_type'], $r['position']];
            }
        }
        if (!empty($relRows)) {
            $zip->addFromString('beziehungen.csv', $this->buildCsv(
                ['SKU', 'Ziel-SKU', 'Beziehungstyp', 'Position'],
                $relRows,
            ));
        }

        // Medien CSV
        $mediaRows = [];
        foreach ($products as $p) {
            foreach ($p['media'] ?? [] as $m) {
                $mediaRows[] = [$p['sku'], $m['file_name'], $m['usage_type'], $m['position']];
            }
        }
        if (!empty($mediaRows)) {
            $zip->addFromString('medien.csv', $this->buildCsv(
                ['SKU', 'Dateiname', 'Verwendungstyp', 'Position'],
                $mediaRows,
            ));
        }

        $zip->close();

        return new StreamedResponse(function () use ($tmpFile) {
            readfile($tmpFile);
            @unlink($tmpFile);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$fullName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    private function buildCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($handle, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
