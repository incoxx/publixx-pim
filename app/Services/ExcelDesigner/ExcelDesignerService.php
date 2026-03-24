<?php

declare(strict_types=1);

namespace App\Services\ExcelDesigner;

use App\Models\ExcelTemplate;
use App\Models\SearchProfile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelDesignerService
{
    public function __construct(
        private readonly ExcelDataCollector $dataCollector,
        private readonly ExcelSheetWriter $sheetWriter,
    ) {}

    /**
     * Excel-Datei zum Download generieren.
     */
    public function download(ExcelTemplate $template, ?SearchProfile $searchProfile = null): StreamedResponse
    {
        $data = $this->dataCollector->collect($template, $searchProfile);

        $spreadsheet = $this->sheetWriter->build(
            $data['grouped'],
            $template->template_json,
            $template->language ?? 'de',
            $template->excel_settings ?? [],
        );

        $fileName = Str::slug($template->name) . '-' . date('Y-m-d');

        return $this->sheetWriter->buildResponse($spreadsheet, $fileName);
    }

    /**
     * Vorschau-Daten generieren (Spaltenköpfe + Beispielzeilen).
     */
    public function preview(ExcelTemplate $template, ?SearchProfile $searchProfile = null, int $limit = 5): array
    {
        $data = $this->dataCollector->collect($template, $searchProfile, $limit);
        $language = $template->language ?? 'de';

        return [
            'total' => $data['total'],
            'sheets' => $this->buildPreviewSheets($data['grouped'], $language),
        ];
    }

    /**
     * Vorschau-Sheets für das Frontend aufbauen.
     */
    private function buildPreviewSheets(array $grouped, string $language): array
    {
        $sheets = [];

        foreach ($grouped as $group) {
            $columns = $group['definition']['columns'] ?? [];
            $sheetPreview = [
                'label' => $group['value'] ?: $group['label'] ?: 'Blatt',
                'columns' => array_map(fn ($col) => [
                    'id' => $col['id'] ?? '',
                    'label' => $col['label'] ?? '',
                    'type' => $col['type'] ?? 'static',
                ], $columns),
                'rows' => [],
                'count' => $group['count'] ?? 0,
            ];

            // Beispielzeilen aus Produkten
            $products = $group['products'] ?? [];
            foreach (array_slice($products, 0, 5) as $product) {
                if (!$product instanceof \App\Models\Product) {
                    continue;
                }
                $row = [];
                foreach ($columns as $column) {
                    $row[] = $this->resolvePreviewValue($product, $column, $language);
                }
                $sheetPreview['rows'][] = $row;
            }

            // Untergruppen-Produkte einbeziehen
            if (empty($products) && !empty($group['subgroups'])) {
                foreach ($group['subgroups'] as $sub) {
                    foreach (array_slice($sub['products'] ?? [], 0, 3) as $product) {
                        if (!$product instanceof \App\Models\Product) {
                            continue;
                        }
                        $row = [];
                        foreach ($columns as $column) {
                            $row[] = $this->resolvePreviewValue($product, $column, $language);
                        }
                        $sheetPreview['rows'][] = $row;
                    }
                    if (count($sheetPreview['rows']) >= 5) {
                        break;
                    }
                }
            }

            $sheets[] = $sheetPreview;
        }

        return $sheets;
    }

    /**
     * Vorschau-Wert für eine Zelle auflösen (vereinfacht, ohne Bilder).
     */
    private function resolvePreviewValue(\App\Models\Product $product, array $column, string $language): string
    {
        $type = $column['type'] ?? 'static';

        return match ($type) {
            'field' => app(\App\Services\Report\ElementRenderer::class)
                ->resolveFieldValue($product, $column['field'] ?? '', $language),
            'attribute' => $this->resolvePreviewAttribute($product, $column, $language),
            'price' => $this->resolvePreviewPrice($product, $column),
            'image' => $column['renderMode'] ?? 'embedded' === 'url'
                ? '[Bild-URL]' : '[Thumbnail]',
            'media' => '[Medien]',
            'relation' => '[Relationen]',
            'variant' => '[Varianten]',
            'static' => $column['defaultValue'] ?? '',
            'text' => $column['content'] ?? '',
            default => '',
        };
    }

    private function resolvePreviewAttribute(\App\Models\Product $product, array $column, string $language): string
    {
        $resolved = app(\App\Services\Report\ElementRenderer::class)
            ->resolveAttributeValue($product, $column['attributeId'] ?? '', $language);
        $value = $resolved['value'];
        if (!empty($column['includeUnit']) && !empty($resolved['unit'])) {
            $value = "{$value} {$resolved['unit']}";
        }
        return $value;
    }

    private function resolvePreviewPrice(\App\Models\Product $product, array $column): string
    {
        $priceTypeName = $column['priceType'] ?? '';
        $price = $product->prices
            ->first(fn ($p) => $p->priceType?->technical_name === $priceTypeName);
        return $price ? number_format((float) $price->amount, 2, ',', '.') : '';
    }
}
