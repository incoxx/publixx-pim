<?php

declare(strict_types=1);

namespace App\Services\ExcelDesigner;

use App\Models\MediaUsageType;
use App\Models\Product;
use App\Models\ProductRelationType;
use App\Services\Report\ElementRenderer;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelSheetWriter
{
    /** @var array<string, string> Cache: usage_type technical_name → id */
    private array $usageTypeCache = [];

    public function __construct(
        private readonly ElementRenderer $elementRenderer,
    ) {}

    /**
     * Gruppierte Daten in ein Spreadsheet-Objekt rendern.
     */
    public function build(array $grouped, array $templateJson, string $language, array $excelSettings = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($grouped as $index => $group) {
            $this->writeGroupSheet($spreadsheet, $group, $language, $excelSettings, $index);
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $spreadsheet->createSheet()->setTitle('Leer');
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Spreadsheet als StreamedResponse zurückgeben.
     */
    public function buildResponse(Spreadsheet $spreadsheet, string $fileName): StreamedResponse
    {
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

    /**
     * Ein Sheet für eine Top-Level-Gruppe schreiben.
     */
    private function writeGroupSheet(Spreadsheet $spreadsheet, array $group, string $language, array $settings, int $index): void
    {
        // Label hat Vorrang, wenn vom Nutzer gesetzt. Sonst Gruppenwert, sonst Fallback.
        $label = $group['label'] ?? '';
        $value = $group['value'] ?? '';
        $sheetTitle = $this->sanitizeSheetTitle(
            $label ?: $value ?: 'Blatt ' . ($index + 1),
            $spreadsheet
        );
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetTitle);

        $columns = $group['definition']['columns'] ?? [];
        // Spalten aufklappen (expand-Modus berechnen)
        $expandedColumns = $this->expandColumns($columns, $group);

        $row = 1;

        // Header-Zeilen
        $row = $this->writeHeaderRows($sheet, $group, $expandedColumns, $language, $row);

        // Spaltenköpfe
        $headerRow = $row;
        $row = $this->writeColumnHeaders($sheet, $expandedColumns, $row, $settings);

        // Produkte
        if (!empty($group['products'])) {
            foreach ($group['products'] as $product) {
                if ($product instanceof Product) {
                    $row = $this->writeProductRow($sheet, $product, $expandedColumns, $language, $row);
                }
            }
        }

        // Untergruppen
        if (!empty($group['subgroups'])) {
            $row = $this->writeSubgroups($sheet, $group['subgroups'], $expandedColumns, $language, $row, 1);
        }

        // Footer-Zeilen
        $row = $this->writeFooterRows($sheet, $group, $expandedColumns, $language, $row);

        // Spaltenbreite
        $this->applyColumnWidths($sheet, $expandedColumns);

        // Freeze Panes
        if ($settings['freezeHeader'] ?? true) {
            $sheet->freezePane('A' . ($headerRow + 1));
        }

        // Auto-Filter
        if (($settings['autoFilter'] ?? true) && count($expandedColumns) > 0) {
            $lastCol = $this->colLetter(count($expandedColumns));
            $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");
        }
    }

    /**
     * Spaltenköpfe schreiben.
     */
    private function writeColumnHeaders(Worksheet $sheet, array $columns, int $row, array $settings): int
    {
        $bgColor = $settings['headerStyle']['bgColor'] ?? '4472C4';
        $fontColor = $settings['headerStyle']['fontColor'] ?? 'FFFFFF';
        $bold = $settings['headerStyle']['bold'] ?? true;

        foreach ($columns as $colIdx => $column) {
            $cell = $this->cellRef($colIdx, $row);
            $sheet->setCellValue($cell, $column['label'] ?? '');

            $style = $sheet->getStyle($cell);
            $style->getFont()->setBold($bold)->getColor()->setARGB('FF' . $fontColor);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $bgColor);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        }

        return $row + 1;
    }

    /**
     * Eine Produktzeile schreiben.
     */
    private function writeProductRow(Worksheet $sheet, Product $product, array $columns, string $language, int $row): int
    {
        $hasImage = false;
        $maxThumbnailHeight = 0;

        foreach ($columns as $colIdx => $column) {
            $cell = $this->cellRef($colIdx, $row);
            $type = $column['type'] ?? 'static';

            match ($type) {
                'field' => $this->writeFieldValue($sheet, $cell, $product, $column, $language),
                'attribute' => $this->writeAttributeValue($sheet, $cell, $product, $column, $language),
                'price' => $this->writePriceValue($sheet, $cell, $product, $column),
                'image' => $this->writeImageValue($sheet, $product, $column, $cell, $row, $hasImage, $maxThumbnailHeight),
                'media' => $this->writeMediaValue($sheet, $cell, $product, $column),
                'relation' => $this->writeRelationValue($sheet, $cell, $product, $column),
                'variant' => $this->writeVariantValue($sheet, $cell, $product, $column),
                'static' => $sheet->setCellValue($cell, $column['defaultValue'] ?? ''),
                'text' => $this->writeTextValue($sheet, $cell, $product, $column, $language),
                default => null,
            };
        }

        // Zeilenhöhe anpassen für Thumbnails
        if ($hasImage && $maxThumbnailHeight > 0) {
            $sheet->getRowDimension($row)->setRowHeight($maxThumbnailHeight * 0.75 + 4);
        }

        return $row + 1;
    }

    // --- Wert-Auflösung pro Typ ---

    private function writeFieldValue(Worksheet $sheet, string $cell, Product $product, array $column, string $language): void
    {
        $field = $column['field'] ?? '';

        // Erweiterte Felder die der ElementRenderer nicht kennt
        $value = match ($field) {
            'parent_sku' => $product->parentProduct?->sku ?? '',
            'parent_name' => $product->parentProduct?->name ?? '',
            'manufacturer' => $product->manufacturer?->{"name_{$language}"} ?? $product->manufacturer?->name_de ?? '',
            'product_type_ref' => $product->product_type_ref ?? '',
            default => $this->elementRenderer->resolveFieldValue($product, $field, $language),
        };

        $sheet->setCellValue($cell, $value);
    }

    private function writeAttributeValue(Worksheet $sheet, string $cell, Product $product, array $column, string $language): void
    {
        $resolved = $this->elementRenderer->resolveAttributeValue($product, $column['attributeId'] ?? '', $language);
        $value = $resolved['value'];

        if (!empty($column['includeUnit']) && !empty($resolved['unit'])) {
            $value = "{$value} {$resolved['unit']}";
        }

        $sheet->setCellValue($cell, $this->castForExcel($value, $column['dataType'] ?? 'string'));
    }

    private function writePriceValue(Worksheet $sheet, string $cell, Product $product, array $column): void
    {
        $priceTypeName = $column['priceType'] ?? '';
        $price = $product->prices
            ->first(fn ($p) => $p->priceType?->technical_name === $priceTypeName);

        $sheet->setCellValue($cell, $price ? (float) $price->amount : '');

        if ($price) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    private function writeImageValue(Worksheet $sheet, Product $product, array $column, string $cell, int $row, bool &$hasImage, int &$maxHeight): void
    {
        $renderMode = $column['renderMode'] ?? 'embedded';
        $usageTypeName = $column['mediaUsageType'] ?? '';
        $height = (int) ($column['thumbnailHeight'] ?? 50);

        $media = $this->findMediaByUsageType($product, $usageTypeName);

        if (!$media) {
            $sheet->setCellValue($cell, '');
            return;
        }

        if ($renderMode === 'url') {
            $sheet->setCellValue($cell, $media->file_path ?? '');
            return;
        }

        // Eingebettetes Bild
        $disk = Storage::disk('public');
        $filePath = $media->file_path;

        if (!$filePath || !$disk->exists($filePath)) {
            $sheet->setCellValue($cell, '');
            return;
        }

        $absolutePath = $disk->path($filePath);

        try {
            $drawing = new Drawing();
            $drawing->setPath($absolutePath);
            $drawing->setHeight($height);
            $drawing->setCoordinates($cell);
            $drawing->setOffsetX(2);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet);

            $hasImage = true;
            $maxHeight = max($maxHeight, $height);
        } catch (\Throwable) {
            // Bild konnte nicht eingebettet werden — Fallback auf Pfad
            $sheet->setCellValue($cell, $filePath);
        }
    }

    private function writeMediaValue(Worksheet $sheet, string $cell, Product $product, array $column): void
    {
        $usageTypeName = $column['mediaUsageType'] ?? '';
        $arrayMode = $column['arrayMode'] ?? 'single';
        $expandIndex = $column['_expandIndex'] ?? null;

        if ($usageTypeName) {
            $mediaItems = $this->findAllMediaByUsageType($product, $usageTypeName);
        } else {
            $mediaItems = $product->media ?? collect();
        }

        $paths = $mediaItems->pluck('file_path')->filter()->values();

        $value = match (true) {
            $expandIndex !== null => $paths->get($expandIndex, ''),
            $arrayMode === 'single' => $paths->first() ?? '',
            $arrayMode === 'join' => $paths->implode(', '),
            default => $paths->first() ?? '',
        };

        $sheet->setCellValue($cell, $value);
    }

    private function writeRelationValue(Worksheet $sheet, string $cell, Product $product, array $column): void
    {
        $relTypeName = $column['relationType'] ?? '';
        $displayField = $column['displayField'] ?? 'sku';
        $arrayMode = $column['arrayMode'] ?? 'join';
        $expandIndex = $column['_expandIndex'] ?? null;

        $relations = $product->outgoingRelations
            ->filter(fn ($r) => !$relTypeName || $r->relationType?->technical_name === $relTypeName);

        $values = $relations->map(fn ($r) => match ($displayField) {
            'name' => $r->targetProduct?->name ?? '',
            'ean' => $r->targetProduct?->ean ?? '',
            default => $r->targetProduct?->sku ?? '',
        })->filter()->values();

        $value = match (true) {
            $expandIndex !== null => $values->get($expandIndex, ''),
            $arrayMode === 'single' => $values->first() ?? '',
            $arrayMode === 'join' => $values->implode(', '),
            default => $values->first() ?? '',
        };

        $sheet->setCellValue($cell, $value);
    }

    private function writeVariantValue(Worksheet $sheet, string $cell, Product $product, array $column): void
    {
        $displayField = $column['displayField'] ?? 'sku';
        $arrayMode = $column['arrayMode'] ?? 'join';
        $expandIndex = $column['_expandIndex'] ?? null;

        $values = ($product->variants ?? collect())->map(fn ($v) => match ($displayField) {
            'name' => $v->name ?? '',
            'ean' => $v->ean ?? '',
            default => $v->sku ?? '',
        })->filter()->values();

        $value = match (true) {
            $expandIndex !== null => $values->get($expandIndex, ''),
            $arrayMode === 'single' => $values->first() ?? '',
            'join' => $values->implode(', '),
            default => $values->first() ?? '',
        };

        $sheet->setCellValue($cell, $value);
    }

    private function writeTextValue(Worksheet $sheet, string $cell, Product $product, array $column, string $language): void
    {
        $template = $column['content'] ?? '';
        $value = $this->elementRenderer->resolveText($template, [
            'sku' => $product->sku ?? '',
            'name' => $product->name ?? '',
            'ean' => $product->ean ?? '',
            'status' => $product->status ?? '',
        ]);
        $sheet->setCellValue($cell, $value);
    }

    // --- Header / Footer / Subgruppen ---

    private function writeHeaderRows(Worksheet $sheet, array $group, array $columns, string $language, int $row): int
    {
        $headerRows = $group['definition']['headerRows'] ?? [];
        if (empty($headerRows)) {
            return $row;
        }

        $colCount = max(count($columns), 1);
        $lastCol = $this->colLetter($colCount);

        foreach ($headerRows as $headerRow) {
            $content = $headerRow['content'] ?? '';
            $resolved = $this->elementRenderer->resolveText($content, [
                'count' => $group['count'] ?? 0,
                'group.label' => $group['label'] ?? '',
                'group.value' => $group['value'] ?? '',
            ]);

            $cell = "A{$row}";
            $sheet->setCellValue($cell, $resolved);
            if ($colCount > 1) {
                $sheet->mergeCells("{$cell}:{$lastCol}{$row}");
            }
            $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(12);
            $row++;
        }

        return $row;
    }

    private function writeFooterRows(Worksheet $sheet, array $group, array $columns, string $language, int $row): int
    {
        $footerRows = $group['definition']['footerRows'] ?? [];
        if (empty($footerRows)) {
            return $row;
        }

        $colCount = max(count($columns), 1);
        $lastCol = $this->colLetter($colCount);

        foreach ($footerRows as $footerRow) {
            $content = $footerRow['content'] ?? '';
            $resolved = $this->elementRenderer->resolveText($content, [
                'count' => $group['count'] ?? 0,
                'group.label' => $group['label'] ?? '',
                'group.value' => $group['value'] ?? '',
            ]);

            $cell = "A{$row}";
            $sheet->setCellValue($cell, $resolved);
            if ($colCount > 1) {
                $sheet->mergeCells("{$cell}:{$lastCol}{$row}");
            }
            $sheet->getStyle($cell)->getFont()->setItalic(true);
            $row++;
        }

        return $row;
    }

    private function writeSubgroups(Worksheet $sheet, array $subgroups, array $columns, string $language, int $row, int $depth): int
    {
        foreach ($subgroups as $subgroup) {
            // Untergruppen-Trennzeile
            $colCount = max(count($columns), 1);
            $lastCol = $this->colLetter($colCount);
            $indent = str_repeat('  ', $depth);
            $label = $indent . ($subgroup['value'] ?: $subgroup['label'] ?: '');

            $cell = "A{$row}";
            $sheet->setCellValue($cell, $label);
            if ($colCount > 1) {
                $sheet->mergeCells("{$cell}:{$lastCol}{$row}");
            }
            $style = $sheet->getStyle($cell);
            $style->getFont()->setBold(true);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8E8E8');
            $row++;

            // Produkte der Untergruppe
            if (!empty($subgroup['products'])) {
                foreach ($subgroup['products'] as $product) {
                    if ($product instanceof Product) {
                        $row = $this->writeProductRow($sheet, $product, $columns, $language, $row);
                    }
                }
            }

            // Tiefere Untergruppen
            if (!empty($subgroup['subgroups'])) {
                $row = $this->writeSubgroups($sheet, $subgroup['subgroups'], $columns, $language, $row, $depth + 1);
            }
        }

        return $row;
    }

    // --- Hilfsmethoden ---

    /**
     * Spalten mit arrayMode=expand aufklappen.
     * Erzeugt mehrere Spalten aus einer expand-Definition.
     */
    private function expandColumns(array $columns, array $group): array
    {
        $expanded = [];
        $maxCounts = $this->calculateMaxArrayCounts($columns, $group);

        foreach ($columns as $column) {
            $arrayMode = $column['arrayMode'] ?? null;

            if ($arrayMode !== 'expand') {
                $expanded[] = $column;
                continue;
            }

            $colId = $column['id'] ?? '';
            $maxItems = $maxCounts[$colId] ?? 1;
            $maxColumns = (int) ($column['maxColumns'] ?? 10);
            $count = min($maxItems, $maxColumns);

            for ($i = 0; $i < max($count, 1); $i++) {
                $expandedCol = $column;
                $expandedCol['_expandIndex'] = $i;
                $expandedCol['label'] = ($column['label'] ?? '') . ' ' . ($i + 1);
                $expandedCol['arrayMode'] = 'single'; // Einzelwert pro Spalte
                $expanded[] = $expandedCol;
            }
        }

        return $expanded;
    }

    /**
     * Maximale Anzahl Array-Einträge pro expand-Spalte berechnen.
     */
    private function calculateMaxArrayCounts(array $columns, array $group): array
    {
        $counts = [];
        $products = $this->collectAllProducts($group);

        foreach ($columns as $column) {
            if (($column['arrayMode'] ?? null) !== 'expand') {
                continue;
            }

            $colId = $column['id'] ?? '';
            $max = 0;

            foreach ($products as $product) {
                if (!$product instanceof Product) {
                    continue;
                }

                $itemCount = match ($column['type'] ?? '') {
                    'media' => $this->countMediaItems($product, $column),
                    'relation' => $this->countRelationItems($product, $column),
                    'variant' => ($product->variants ?? collect())->count(),
                    default => 0,
                };

                $max = max($max, $itemCount);
            }

            $counts[$colId] = $max;
        }

        return $counts;
    }

    private function collectAllProducts(array $group): array
    {
        $products = $group['products'] ?? [];
        foreach ($group['subgroups'] ?? [] as $sub) {
            $products = array_merge($products, $this->collectAllProducts($sub));
        }
        return $products;
    }

    private function countMediaItems(Product $product, array $column): int
    {
        $usageTypeName = $column['mediaUsageType'] ?? '';
        if ($usageTypeName) {
            return $this->findAllMediaByUsageType($product, $usageTypeName)->count();
        }
        return ($product->media ?? collect())->count();
    }

    private function countRelationItems(Product $product, array $column): int
    {
        $relTypeName = $column['relationType'] ?? '';
        if ($relTypeName) {
            return $product->outgoingRelations
                ->filter(fn ($r) => $r->relationType?->technical_name === $relTypeName)
                ->count();
        }
        return ($product->outgoingRelations ?? collect())->count();
    }

    private function resolveUsageTypeId(string $usageTypeName): ?string
    {
        if (!isset($this->usageTypeCache[$usageTypeName])) {
            $usageType = MediaUsageType::where('technical_name', $usageTypeName)->first();
            $this->usageTypeCache[$usageTypeName] = $usageType?->id;
        }
        return $this->usageTypeCache[$usageTypeName];
    }

    private function findMediaByUsageType(Product $product, string $usageTypeName): mixed
    {
        if (!$usageTypeName) {
            return $product->media?->first();
        }

        $usageTypeId = $this->resolveUsageTypeId($usageTypeName);
        if (!$usageTypeId) {
            return null;
        }

        return $product->media
            ->first(fn ($m) => $m->pivot?->usage_type_id === $usageTypeId);
    }

    private function findAllMediaByUsageType(Product $product, string $usageTypeName): \Illuminate\Support\Collection
    {
        if (!$usageTypeName) {
            return $product->media ?? collect();
        }

        $usageTypeId = $this->resolveUsageTypeId($usageTypeName);
        if (!$usageTypeId) {
            return collect();
        }

        return ($product->media ?? collect())
            ->filter(fn ($m) => $m->pivot?->usage_type_id === $usageTypeId)
            ->values();
    }

    private function castForExcel(mixed $value, string $dataType): mixed
    {
        if ($value === '' || $value === null) {
            return '';
        }

        return match ($dataType) {
            'number' => is_numeric($value) ? (float) $value : $value,
            'integer' => is_numeric($value) ? (int) $value : $value,
            'boolean' => is_bool($value) ? $value : (bool) $value,
            default => (string) $value,
        };
    }

    private function applyColumnWidths(Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $colIdx => $column) {
            $colLetter = $this->colLetter($colIdx + 1);
            $width = $column['width'] ?? null;

            if ($width) {
                $sheet->getColumnDimension($colLetter)->setWidth((int) $width);
            } else {
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
        }
    }

    /**
     * Sheet-Titel bereinigen (max 31 Zeichen, keine ungültigen Zeichen).
     */
    private function sanitizeSheetTitle(string $title, Spreadsheet $spreadsheet): string
    {
        // Ungültige Zeichen entfernen
        $title = str_replace(['/', '\\', '?', '*', '[', ']', ':'], '-', $title);
        $title = mb_substr(trim($title), 0, 31);

        if (empty($title)) {
            $title = 'Blatt';
        }

        // Eindeutigkeit sicherstellen
        $existingNames = [];
        for ($i = 0; $i < $spreadsheet->getSheetCount(); $i++) {
            $existingNames[] = $spreadsheet->getSheet($i)->getTitle();
        }

        $baseName = $title;
        $suffix = 2;
        while (in_array($title, $existingNames, true)) {
            $title = mb_substr($baseName, 0, 31 - strlen(" ({$suffix})")) . " ({$suffix})";
            $suffix++;
        }

        return $title;
    }

    /**
     * Zellreferenz aus Spaltenindex (0-basiert) und Zeile (1-basiert).
     */
    private function cellRef(int $colIndex, int $row): string
    {
        return $this->colLetter($colIndex + 1) . $row;
    }

    /**
     * Spalten-Buchstabe aus 1-basiertem Index (1=A, 2=B, ..., 27=AA).
     */
    private function colLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $colNumber--;
            $letter = chr(65 + ($colNumber % 26)) . $letter;
            $colNumber = intdiv($colNumber, 26);
        }
        return $letter;
    }
}
