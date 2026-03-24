<?php

declare(strict_types=1);

namespace App\Services\ExcelDesigner;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelTemplateImporter
{
    /**
     * Excel-Vorlage importieren und Spaltenköpfe als Template-JSON extrahieren.
     *
     * Liest die erste Zeile jedes Sheets und erzeugt eine Template-Struktur
     * mit static-Spalten, die der Nutzer dann auf PIM-Felder mappen kann.
     */
    public function import(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheets = [];

        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $columns = [];
            $rowIterator = $worksheet->getRowIterator(1, 1);

            if (!$rowIterator->valid()) {
                continue;
            }

            $headerRow = $rowIterator->current();
            $cellIterator = $headerRow->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);

            foreach ($cellIterator as $cell) {
                $value = $cell->getValue();
                if ($value === null || $value === '') {
                    continue;
                }

                $columns[] = [
                    'id' => 'col_' . Str::random(8),
                    'type' => 'static',
                    'label' => (string) $value,
                    'defaultValue' => '',
                    'dataType' => 'string',
                    'width' => null,
                ];
            }

            if (empty($columns)) {
                continue;
            }

            $sheets[] = [
                'id' => 'sh_' . Str::random(8),
                'label' => $worksheet->getTitle(),
                'field' => 'none',
                'sortOrder' => 'asc',
                'columns' => $columns,
                'headerRows' => [],
                'footerRows' => [],
                'sheets' => [],
            ];
        }

        // Mindestens ein Sheet sicherstellen
        if (empty($sheets)) {
            $sheets[] = [
                'id' => 'sh_' . Str::random(8),
                'label' => 'Blatt 1',
                'field' => 'none',
                'sortOrder' => 'asc',
                'columns' => [],
                'headerRows' => [],
                'footerRows' => [],
                'sheets' => [],
            ];
        }

        return [
            'version' => 1,
            'sheets' => $sheets,
        ];
    }
}
