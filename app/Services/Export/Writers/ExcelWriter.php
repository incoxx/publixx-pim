<?php

declare(strict_types=1);

namespace App\Services\Export\Writers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelWriter
{
    public function write(array $data, string $fileName): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $this->writeProductsSheet($spreadsheet, $data['products'] ?? []);
        $this->writeAttributesSheet($spreadsheet, $data['products'] ?? []);
        $this->writePricesSheet($spreadsheet, $data['products'] ?? []);
        $this->writeRelationsSheet($spreadsheet, $data['products'] ?? []);
        $this->writeMediaSheet($spreadsheet, $data['products'] ?? []);

        // Leeres Default-Sheet entfernen falls weitere Sheets vorhanden
        if ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(0);
        }

        $fullName = $fileName . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fullName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    private function writeProductsSheet(Spreadsheet $spreadsheet, array $products): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Produkte');

        $headers = ['SKU', 'Name', 'Status', 'EAN', 'Produkttyp'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        foreach ($products as $product) {
            $sheet->setCellValue([1, $row], $product['sku'] ?? '');
            $sheet->setCellValue([2, $row], $product['name'] ?? '');
            $sheet->setCellValue([3, $row], $product['status'] ?? '');
            $sheet->setCellValue([4, $row], $product['ean'] ?? '');
            $sheet->setCellValue([5, $row], $product['product_type'] ?? '');
            $row++;
        }
    }

    private function writeAttributesSheet(Spreadsheet $spreadsheet, array $products): void
    {
        $hasAttributes = false;
        foreach ($products as $p) {
            if (!empty($p['attributes'])) {
                $hasAttributes = true;
                break;
            }
        }
        if (!$hasAttributes) {
            return;
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Attributwerte');

        $headers = ['SKU', 'Attribut', 'Attributname', 'Wert', 'Sprache'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        foreach ($products as $product) {
            foreach ($product['attributes'] ?? [] as $attr) {
                $sheet->setCellValue([1, $row], $product['sku']);
                $sheet->setCellValue([2, $row], $attr['attribute']);
                $sheet->setCellValue([3, $row], $attr['attribute_name']);
                $sheet->setCellValue([4, $row], $attr['value']);
                $sheet->setCellValue([5, $row], $attr['language']);
                $row++;
            }
        }
    }

    private function writePricesSheet(Spreadsheet $spreadsheet, array $products): void
    {
        $hasPrices = false;
        foreach ($products as $p) {
            if (!empty($p['prices'])) {
                $hasPrices = true;
                break;
            }
        }
        if (!$hasPrices) {
            return;
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Preise');

        $headers = ['SKU', 'Preistyp', 'Betrag', 'Währung', 'Ab Menge', 'Gültig von', 'Gültig bis'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        foreach ($products as $product) {
            foreach ($product['prices'] ?? [] as $price) {
                $sheet->setCellValue([1, $row], $product['sku']);
                $sheet->setCellValue([2, $row], $price['price_type']);
                $sheet->setCellValue([3, $row], $price['amount']);
                $sheet->setCellValue([4, $row], $price['currency']);
                $sheet->setCellValue([5, $row], $price['scale_from']);
                $sheet->setCellValue([6, $row], $price['valid_from']);
                $sheet->setCellValue([7, $row], $price['valid_to']);
                $row++;
            }
        }
    }

    private function writeRelationsSheet(Spreadsheet $spreadsheet, array $products): void
    {
        $hasRelations = false;
        foreach ($products as $p) {
            if (!empty($p['relations'])) {
                $hasRelations = true;
                break;
            }
        }
        if (!$hasRelations) {
            return;
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Beziehungen');

        $headers = ['SKU', 'Ziel-SKU', 'Beziehungstyp', 'Position'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        foreach ($products as $product) {
            foreach ($product['relations'] ?? [] as $rel) {
                $sheet->setCellValue([1, $row], $product['sku']);
                $sheet->setCellValue([2, $row], $rel['target_sku']);
                $sheet->setCellValue([3, $row], $rel['relation_type']);
                $sheet->setCellValue([4, $row], $rel['position']);
                $row++;
            }
        }
    }

    private function writeMediaSheet(Spreadsheet $spreadsheet, array $products): void
    {
        $hasMedia = false;
        foreach ($products as $p) {
            if (!empty($p['media'])) {
                $hasMedia = true;
                break;
            }
        }
        if (!$hasMedia) {
            return;
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Medien');

        $headers = ['SKU', 'Dateiname', 'Verwendungstyp', 'Position'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        foreach ($products as $product) {
            foreach ($product['media'] ?? [] as $m) {
                $sheet->setCellValue([1, $row], $product['sku']);
                $sheet->setCellValue([2, $row], $m['file_name']);
                $sheet->setCellValue([3, $row], $m['usage_type']);
                $sheet->setCellValue([4, $row], $m['position']);
                $row++;
            }
        }
    }
}
