<?php

declare(strict_types=1);

namespace App\Services\Export\Writers;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class CsvWriter
{
    /**
     * Neutralisiert Formel-Injection (OWASP CSV Injection): Zellen, die mit
     * =, +, -, @, Tab oder CR beginnen, werden mit Apostroph geprefixt,
     * damit Excel/Calc sie nicht als Formel ausführt. Echte Zahlen
     * (z.B. -5) bleiben unverändert.
     */
    private function sanitizeRow(array $row): array
    {
        return array_map(function ($cell) {
            if (is_string($cell) && $cell !== '' && !is_numeric($cell)
                && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $cell;
            }

            return $cell;
        }, $row);
    }

    private function putRow($handle, array $row): void
    {
        fputcsv($handle, $this->sanitizeRow($row), ';');
    }

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
                $this->putRow($handle, [
                    $product['sku'] ?? '',
                    $product['name'] ?? '',
                    $product['status'] ?? '',
                    $product['ean'] ?? '',
                    $product['product_type'] ?? '',
                ]);
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

        // Temp-Dateien anlegen — kein CSV-String im RAM
        $tmpFiles = [];
        $handles  = [];

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);

        // Produkte (immer vorhanden)
        $tmpFiles['products'] = tempnam(sys_get_temp_dir(), 'exp_prod_');
        $handles['products']  = fopen($tmpFiles['products'], 'w');
        fwrite($handles['products'], $bom);
        fputcsv($handles['products'], ['SKU', 'Name', 'Status', 'EAN', 'Produkttyp'], ';');

        // Prüfen welche Sektionen Daten enthalten
        $hasAttr      = false;
        $hasPrices    = false;
        $hasRelations = false;
        $hasMedia     = false;

        foreach ($products as $p) {
            if (!$hasAttr      && !empty($p['attributes'])) { $hasAttr      = true; }
            if (!$hasPrices    && !empty($p['prices']))     { $hasPrices    = true; }
            if (!$hasRelations && !empty($p['relations']))  { $hasRelations = true; }
            if (!$hasMedia     && !empty($p['media']))      { $hasMedia     = true; }
            if ($hasAttr && $hasPrices && $hasRelations && $hasMedia) { break; }
        }

        if ($hasAttr) {
            $tmpFiles['attr'] = tempnam(sys_get_temp_dir(), 'exp_attr_');
            $handles['attr']  = fopen($tmpFiles['attr'], 'w');
            fwrite($handles['attr'], $bom);
            fputcsv($handles['attr'], ['SKU', 'Attribut', 'Attributname', 'Wert', 'Sprache'], ';');
        }

        if ($hasPrices) {
            $tmpFiles['prices'] = tempnam(sys_get_temp_dir(), 'exp_price_');
            $handles['prices']  = fopen($tmpFiles['prices'], 'w');
            fwrite($handles['prices'], $bom);
            fputcsv($handles['prices'], ['SKU', 'Preistyp', 'Betrag', 'Währung', 'Ab Menge', 'Gültig von', 'Gültig bis'], ';');
        }

        if ($hasRelations) {
            $tmpFiles['relations'] = tempnam(sys_get_temp_dir(), 'exp_rel_');
            $handles['relations']  = fopen($tmpFiles['relations'], 'w');
            fwrite($handles['relations'], $bom);
            fputcsv($handles['relations'], ['SKU', 'Ziel-SKU', 'Beziehungstyp', 'Position'], ';');
        }

        if ($hasMedia) {
            $tmpFiles['media'] = tempnam(sys_get_temp_dir(), 'exp_media_');
            $handles['media']  = fopen($tmpFiles['media'], 'w');
            fwrite($handles['media'], $bom);
            fputcsv($handles['media'], ['SKU', 'Dateiname', 'Verwendungstyp', 'Position'], ';');
        }

        foreach ($products as $p) {
            $this->putRow($handles['products'], [
                $p['sku'] ?? '', $p['name'] ?? '', $p['status'] ?? '',
                $p['ean'] ?? '', $p['product_type'] ?? '',
            ]);

            if (isset($handles['attr'])) {
                foreach ($p['attributes'] ?? [] as $a) {
                    $this->putRow($handles['attr'], [
                        $p['sku'], $a['attribute'], $a['attribute_name'], $a['value'], $a['language'],
                    ]);
                }
            }

            if (isset($handles['prices'])) {
                foreach ($p['prices'] ?? [] as $pr) {
                    $this->putRow($handles['prices'], [
                        $p['sku'], $pr['price_type'], $pr['amount'], $pr['currency'],
                        $pr['scale_from'], $pr['valid_from'], $pr['valid_to'],
                    ]);
                }
            }

            if (isset($handles['relations'])) {
                foreach ($p['relations'] ?? [] as $r) {
                    $this->putRow($handles['relations'], [
                        $p['sku'], $r['target_sku'], $r['relation_type'], $r['position'],
                    ]);
                }
            }

            if (isset($handles['media'])) {
                foreach ($p['media'] ?? [] as $m) {
                    $this->putRow($handles['media'], [
                        $p['sku'], $m['file_name'], $m['usage_type'], $m['position'],
                    ]);
                }
            }
        }

        foreach ($handles as $handle) {
            fclose($handle);
        }

        $zipTmpFile = tempnam(sys_get_temp_dir(), 'export-csv-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipTmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tmpFiles['products'], 'produkte.csv');
        if (isset($tmpFiles['attr']))      { $zip->addFile($tmpFiles['attr'],      'attributwerte.csv'); }
        if (isset($tmpFiles['prices']))    { $zip->addFile($tmpFiles['prices'],    'preise.csv'); }
        if (isset($tmpFiles['relations'])) { $zip->addFile($tmpFiles['relations'], 'beziehungen.csv'); }
        if (isset($tmpFiles['media']))     { $zip->addFile($tmpFiles['media'],     'medien.csv'); }
        $zip->close();

        foreach ($tmpFiles as $tmp) {
            @unlink($tmp);
        }

        return new StreamedResponse(function () use ($zipTmpFile) {
            readfile($zipTmpFile);
            @unlink($zipTmpFile);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$fullName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }
}
