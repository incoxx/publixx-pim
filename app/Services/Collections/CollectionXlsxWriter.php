<?php

declare(strict_types=1);

namespace App\Services\Collections;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Einzelnes Sheet mit Kopfblock, Positionstabelle und Summenzeile fuer eine Collection --
 * bewusst NICHT app/Services/Export/Writers/ExcelWriter.php wiederverwendet: das ist auf
 * mehrblaettrige Produkt-Exports mit fest verdrahtetem 5-Spalten-Schema zugeschnitten,
 * falsche Form fuer ein einblaettriges Positions-plus-Summen-Angebot.
 */
class CollectionXlsxWriter
{
    private const HEADERS = ['Pos.', 'SKU', 'Bezeichnung', 'Menge', 'Einheit', 'Einzelpreis', 'Rabatt %', 'Positionssumme'];

    /**
     * @param  array  $headerVm  {name, reference, organization_name, address_block, display_attributes, currency}
     * @param  array<int, array>  $items  Normalisierte Zeilen aus CollectionRenderService::normalizeItemViewModel()
     */
    public function writeToFile(array $headerVm, array $items, float $grandTotal, string $outputPath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Angebot');

        $row = 1;
        $sheet->setCellValue([1, $row], $headerVm['name'] ?? '');
        $row++;
        if (!empty($headerVm['reference'])) {
            $sheet->setCellValue([1, $row], 'Referenz: ' . $headerVm['reference']);
            $row++;
        }
        if (!empty($headerVm['organization_name'])) {
            $sheet->setCellValue([1, $row], $headerVm['organization_name']);
            $row++;
        }
        foreach ($headerVm['display_attributes'] ?? [] as $attr) {
            $sheet->setCellValue([1, $row], $attr['label'] . ': ' . $attr['value']);
            $row++;
        }

        $row++; // Leerzeile vor der Tabelle
        $tableStartRow = $row;

        foreach (self::HEADERS as $col => $header) {
            $sheet->setCellValue([$col + 1, $tableStartRow], $header);
        }
        $row = $tableStartRow + 1;

        $currency = $headerVm['currency'] ?? 'EUR';

        foreach ($items as $item) {
            $priceWarning = $item['price_warning'] ?? false;

            $sheet->setCellValue([1, $row], $item['position'] ?? '');
            $sheet->setCellValue([2, $row], $item['sku'] ?? '');
            $sheet->setCellValue([3, $row], $item['name'] ?? '');
            $sheet->setCellValue([4, $row], $item['quantity'] ?? '');
            $sheet->setCellValue([5, $row], $item['unit_label'] ?? '');

            if ($priceWarning) {
                $sheet->setCellValue([6, $row], 'Preis auf Anfrage');
                $sheet->setCellValue([8, $row], 'Preis auf Anfrage');
            } else {
                $sheet->setCellValue([6, $row], $this->formatAmount($item['unit_price'] ?? null, $currency));
                $sheet->setCellValue([8, $row], $this->formatAmount($item['line_total'] ?? null, $currency));
            }
            $sheet->setCellValue([7, $row], $item['discount_percent'] ?? '');

            $row++;

            foreach ($item['display_attributes'] ?? [] as $attr) {
                $sheet->setCellValue([3, $row], $attr['label'] . ': ' . $attr['value']);
                $row++;
            }
        }

        $sheet->setCellValue([7, $row], 'Gesamt');
        $sheet->setCellValue([8, $row], $this->formatAmount($grandTotal, $currency));

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    }

    private function formatAmount(?float $amount, string $currency): string
    {
        if ($amount === null) {
            return '';
        }

        return number_format($amount, 2, ',', '.') . ' ' . $currency;
    }
}
