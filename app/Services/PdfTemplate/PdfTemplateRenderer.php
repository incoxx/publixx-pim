<?php

declare(strict_types=1);

namespace App\Services\PdfTemplate;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductRelationType;
use App\Services\Report\ElementRenderer;

class PdfTemplateRenderer
{
    public function __construct(
        private readonly ElementRenderer $elementRenderer,
    ) {}

    /**
     * Resolve all elements in a template for a given product.
     *
     * @return array List of resolved elements with display values
     */
    public function resolveElements(array $templateJson, Product $product, string $language = 'de'): array
    {
        $elements = $templateJson['elements'] ?? [];
        $resolved = [];

        foreach ($elements as $element) {
            $resolved[] = $this->resolveElement($element, $product, $language);
        }

        return $resolved;
    }

    private function resolveElement(array $element, Product $product, string $language): array
    {
        $type = $element['type'] ?? 'text';

        return match ($type) {
            'field' => $this->resolveFieldElement($element, $product, $language),
            'attribute' => $this->resolveAttributeElement($element, $product, $language),
            'price' => $this->resolvePriceElement($element, $product),
            'image' => $this->resolveImageElement($element, $product),
            'text' => $this->resolveTextElement($element, $product, $language),
            'variant_table' => $this->resolveVariantTableElement($element, $product, $language),
            'relation_table' => $this->resolveRelationTableElement($element, $product, $language),
            'attribute_table' => $this->resolveAttributeTableElement($element, $product, $language),
            'smart_table' => $this->resolveSmartTableElement($element, $product, $language),
            'shape' => $element,
            default => $element,
        };
    }

    private function resolveFieldElement(array $element, Product $product, string $language): array
    {
        $field = $element['field'] ?? '';
        $value = $this->elementRenderer->resolveFieldValue($product, $field, $language);

        $displayText = '';
        if (!empty($element['showLabel']) && !empty($element['label'])) {
            $displayText = $element['label'] . ': ';
        }
        $displayText .= $value;

        return array_merge($element, ['displayValue' => $displayText, 'rawValue' => $value]);
    }

    private function resolveAttributeElement(array $element, Product $product, string $language): array
    {
        $attributeId = $element['attributeId'] ?? '';
        $resolved = $this->elementRenderer->resolveAttributeValue($product, $attributeId, $language);

        $parts = [];
        if (!empty($element['showLabel']) && $resolved['label']) {
            $parts[] = $resolved['label'] . ':';
        }
        if (($element['showValue'] ?? true) && $resolved['value'] !== '') {
            $parts[] = $resolved['value'];
        }
        if (!empty($element['showUnit']) && $resolved['unit']) {
            $parts[] = $resolved['unit'];
        }

        return array_merge($element, [
            'displayValue' => implode(' ', $parts),
            'rawValue' => $resolved['value'],
            'rawLabel' => $resolved['label'],
            'rawUnit' => $resolved['unit'],
            'rawValues' => $resolved['values'] ?? [],
        ]);
    }

    private function resolvePriceElement(array $element, Product $product): array
    {
        $priceTypeId = $element['priceTypeId'] ?? '';
        $resolved = $this->elementRenderer->resolvePriceValue($product, $priceTypeId);

        $displayText = '';
        if (!empty($element['showLabel']) && !empty($element['label'])) {
            $displayText = $element['label'] . ': ';
        }
        if ($resolved['amount'] !== null) {
            $displayText .= number_format((float) $resolved['amount'], 2, ',', '.') . ' ' . ($resolved['currency'] ?? 'EUR');
        }

        return array_merge($element, ['displayValue' => $displayText, 'rawValue' => $resolved['amount'], 'currency' => $resolved['currency']]);
    }

    private function resolveImageElement(array $element, Product $product): array
    {
        $source = $element['source'] ?? 'primary';
        $images = [];

        $assignments = $product->mediaAssignments ?? collect();

        if ($source === 'primary') {
            $primary = $assignments->sortBy('position')->first();
            if ($primary?->media) {
                $images[] = $this->getMediaPath($primary->media);
            }
        } else {
            foreach ($assignments->sortBy('position') as $assignment) {
                if ($assignment->media) {
                    $images[] = $this->getMediaPath($assignment->media);
                }
            }
        }

        // Build browser-accessible URLs for canvas preview
        $imageUrls = [];
        $appUrl = rtrim(config('app.url'), '/');
        $basePath = parse_url($appUrl, PHP_URL_PATH) ?: '';
        foreach ($images as $filePath) {
            $publicPrefix = storage_path('app/public/');
            if (str_starts_with($filePath, $publicPrefix)) {
                $imageUrls[] = $basePath . '/storage/' . substr($filePath, strlen($publicPrefix));
            }
        }

        return array_merge($element, ['resolvedImages' => $images, 'imageUrls' => $imageUrls]);
    }

    private function resolveTextElement(array $element, Product $product, string $language): array
    {
        $content = $element['content'] ?? '';

        // Replace basic placeholders
        $content = $this->elementRenderer->resolveText($content, [
            'product.name' => $product->name ?? '',
            'product.sku' => $product->sku ?? '',
            'product.ean' => $product->ean ?? '',
        ]);

        return array_merge($element, ['displayValue' => $content]);
    }

    private function resolveVariantTableElement(array $element, Product $product, string $language): array
    {
        $columns = $element['columns'] ?? ['sku', 'name', 'variant_attributes'];
        $variants = $product->variants ?? collect();

        if ($variants->isEmpty()) {
            return array_merge($element, ['variantTableData' => ['headers' => [], 'rows' => []]]);
        }

        // Load variant attributes definition
        $variantAttributes = collect();
        $includeVariantAttrs = in_array('variant_attributes', $columns);

        if ($includeVariantAttrs) {
            $variantAttributes = Attribute::where('is_variant_attribute', true)
                ->where('is_internal', false)
                ->where('status', 'active')
                ->orderBy('position')
                ->get();
        }

        // Build headers
        $headers = [];
        foreach ($columns as $col) {
            if ($col === 'sku') {
                $headers[] = 'SKU';
            } elseif ($col === 'name') {
                $headers[] = 'Name';
            } elseif ($col === 'variant_attributes') {
                foreach ($variantAttributes as $attr) {
                    $headers[] = $attr->{"name_{$language}"} ?? $attr->name_de ?? $attr->technical_name;
                }
            }
        }

        // Build rows — variants have attributeValues eager-loaded
        $rows = [];
        foreach ($variants->sortBy('sku') as $variant) {
            $row = [];

            foreach ($columns as $col) {
                if ($col === 'sku') {
                    $row[] = $variant->sku ?? '';
                } elseif ($col === 'name') {
                    $row[] = $variant->name ?? '';
                } elseif ($col === 'variant_attributes') {
                    foreach ($variantAttributes as $attr) {
                        $resolved = $this->elementRenderer->resolveAttributeValue($variant, $attr->id, $language);
                        $parts = [];
                        if ($resolved['value'] !== '') {
                            $parts[] = $resolved['value'];
                        }
                        if ($resolved['unit'] !== '') {
                            $parts[] = $resolved['unit'];
                        }
                        $row[] = implode(' ', $parts);
                    }
                }
            }

            $rows[] = $row;
        }

        return array_merge($element, ['variantTableData' => ['headers' => $headers, 'rows' => $rows]]);
    }

    private function resolveRelationTableElement(array $element, Product $product, string $language): array
    {
        $relationTypeId = $element['relationTypeId'] ?? null;
        $columns = $element['columns'] ?? ['sku', 'name'];

        $sortBy = $element['sortBy'] ?? null;
        $sortDirection = $element['sortDirection'] ?? 'asc';

        $relations = $product->outgoingRelations ?? collect();
        if ($relationTypeId) {
            $relations = $relations->where('relation_type_id', $relationTypeId);
        }

        if ($relations->isEmpty()) {
            return array_merge($element, ['variantTableData' => ['headers' => [], 'rows' => []]]);
        }

        // Determine which relation attributes to show
        $relationAttributes = collect();
        if (in_array('relation_attributes', $columns) && $relationTypeId) {
            $relationAttributes = ProductRelationType::find($relationTypeId)
                ?->defaultAttributes()
                ->where('is_internal', false)
                ->orderBy('position')
                ->get() ?? collect();
        }

        // Determine which target product attributes to show
        $productAttributeIds = $element['productAttributeIds'] ?? [];
        $productAttributes = collect();
        if (in_array('product_attributes', $columns) && !empty($productAttributeIds)) {
            $attrs = Attribute::whereIn('id', $productAttributeIds)->get();
            // Preserve the order from productAttributeIds
            $productAttributes = collect($productAttributeIds)
                ->map(fn($id) => $attrs->firstWhere('id', $id))
                ->filter();
        }

        // Build headers
        $headers = [];
        foreach ($columns as $col) {
            if ($col === 'relation_type') {
                $headers[] = 'Beziehungsart';
            } elseif ($col === 'sku') {
                $headers[] = 'SKU';
            } elseif ($col === 'name') {
                $headers[] = 'Name';
            } elseif ($col === 'ean') {
                $headers[] = 'EAN';
            } elseif ($col === 'relation_attributes') {
                foreach ($relationAttributes as $attr) {
                    $headers[] = $attr->{"name_{$language}"} ?? $attr->name_de ?? $attr->technical_name;
                }
            } elseif ($col === 'product_attributes') {
                foreach ($productAttributes as $attr) {
                    $headers[] = $attr->{"name_{$language}"} ?? $attr->name_de ?? $attr->technical_name;
                }
            }
        }

        // Sort relations
        if ($sortBy === 'relation_type') {
            $sorted = $relations->sortBy(fn ($r) => $r->relationType->{"name_{$language}"} ?? $r->relationType->name_de ?? '', SORT_NATURAL | SORT_FLAG_CASE);
            if ($sortDirection === 'desc') $sorted = $sorted->reverse();
            // Secondary sort by target name
            $relations = $sorted->groupBy(fn ($r) => $r->relationType->{"name_{$language}"} ?? '')
                ->flatMap(fn ($group) => $group->sortBy(fn ($r) => $r->targetProduct?->name ?? ''));
        } elseif ($sortBy === 'name') {
            $sorted = $relations->sortBy(fn ($r) => $r->targetProduct?->name ?? '', SORT_NATURAL | SORT_FLAG_CASE);
            if ($sortDirection === 'desc') $sorted = $sorted->reverse();
            $relations = $sorted;
        } elseif ($sortBy === 'sku') {
            $sorted = $relations->sortBy(fn ($r) => $r->targetProduct?->sku ?? '', SORT_NATURAL | SORT_FLAG_CASE);
            if ($sortDirection === 'desc') $sorted = $sorted->reverse();
            $relations = $sorted;
        } else {
            $relations = $relations->sortBy('sort_order');
        }

        // Build rows
        $rows = [];
        foreach ($relations as $relation) {
            $target = $relation->targetProduct;
            if (!$target) {
                continue;
            }

            $row = [];
            foreach ($columns as $col) {
                if ($col === 'relation_type') {
                    $row[] = $relation->relationType?->{"name_{$language}"} ?? $relation->relationType?->name_de ?? '';
                } elseif ($col === 'sku') {
                    $row[] = $target->sku ?? '';
                } elseif ($col === 'name') {
                    $row[] = $target->name ?? '';
                } elseif ($col === 'ean') {
                    $row[] = $target->ean ?? '';
                } elseif ($col === 'relation_attributes') {
                    foreach ($relationAttributes as $attr) {
                        $attrVal = $relation->attributeValues
                            ->where('attribute_id', $attr->id)
                            ->first();

                        if ($attrVal) {
                            $parts = [];
                            $displayValue = match ($attr->data_type) {
                                'String' => $attrVal->value_string,
                                'Number', 'Float', 'Decimal', 'Integer' => $attrVal->value_number !== null
                                    ? rtrim(rtrim((string) $attrVal->value_number, '0'), '.')
                                    : null,
                                'Flag', 'Boolean' => $attrVal->value_flag !== null
                                    ? ($attrVal->value_flag ? 'Ja' : 'Nein')
                                    : null,
                                'Selection', 'ValueList' => $attrVal->valueListEntry
                                    ? ($language === 'en' && $attrVal->valueListEntry->display_value_en
                                        ? $attrVal->valueListEntry->display_value_en
                                        : $attrVal->valueListEntry->display_value_de)
                                    : null,
                                'Dictionary' => $attrVal->dictionaryEntry
                                    ? ($language === 'en' && $attrVal->dictionaryEntry->short_text_en
                                        ? $attrVal->dictionaryEntry->short_text_en
                                        : $attrVal->dictionaryEntry->short_text_de)
                                    : null,
                                default => $attrVal->value_string,
                            };

                            if ($displayValue !== null && $displayValue !== '') {
                                $parts[] = $displayValue;
                            }
                            if ($attrVal->unit?->abbreviation) {
                                $parts[] = $attrVal->unit->abbreviation;
                            }
                            $row[] = implode(' ', $parts);
                        } else {
                            $row[] = '';
                        }
                    }
                } elseif ($col === 'product_attributes') {
                    foreach ($productAttributes as $attr) {
                        $resolved = $this->elementRenderer->resolveAttributeValue($target, $attr->id, $language);
                        $parts = [];
                        if ($resolved['value'] !== '') {
                            $parts[] = $resolved['value'];
                        }
                        if ($resolved['unit'] !== '') {
                            $parts[] = $resolved['unit'];
                        }
                        $row[] = implode(' ', $parts);
                    }
                }
            }

            $rows[] = $row;
        }

        return array_merge($element, ['variantTableData' => ['headers' => $headers, 'rows' => $rows]]);
    }

    private function resolveAttributeTableElement(array $element, Product $product, string $language): array
    {
        $sourceMode = $element['sourceMode'] ?? 'group';
        $attributeGroupId = $element['attributeGroupId'] ?? null;
        $attributeIds = $element['attributeIds'] ?? [];

        // Load attributes based on source mode
        if ($sourceMode === 'group' && $attributeGroupId) {
            $attributes = Attribute::where('attribute_type_id', $attributeGroupId)
                ->where('is_internal', false)
                ->where('status', 'active')
                ->orderBy('position')
                ->get();
        } elseif ($sourceMode === 'individual' && !empty($attributeIds)) {
            $attrs = Attribute::whereIn('id', $attributeIds)->get();
            // Preserve the order from attributeIds
            $attributes = collect($attributeIds)
                ->map(fn ($id) => $attrs->firstWhere('id', $id))
                ->filter();
        } else {
            return array_merge($element, ['variantTableData' => ['headers' => [], 'rows' => []]]);
        }

        if ($attributes->isEmpty()) {
            return array_merge($element, ['variantTableData' => ['headers' => [], 'rows' => []]]);
        }

        // Fixed headers: Attributname | Attributwert | Einheit
        $headers = ['Attributname', 'Attributwert', 'Einheit'];

        // Build rows — one row per attribute
        $rows = [];
        foreach ($attributes as $attr) {
            $resolved = $this->elementRenderer->resolveAttributeValue($product, $attr->id, $language);

            $label = $attr->{"name_{$language}"} ?? $attr->name_de ?? $attr->technical_name;
            $value = $resolved['value'] ?? '';
            $unit = $resolved['unit'] ?? '';

            $rows[] = [$label, $value, $unit];
        }

        return array_merge($element, ['variantTableData' => ['headers' => $headers, 'rows' => $rows]]);
    }

    private function resolveSmartTableElement(array $element, Product $product, string $language): array
    {
        $ptl = $element['ptl'] ?? [];
        $bind = $element['bind'] ?? '';
        $mode = $ptl['mode'] ?? 'normal';

        // Resolve the bound array data from the product
        $arrayData = $this->resolveSmartTableDataSource($bind, $product, $language);

        if (empty($arrayData)) {
            return array_merge($element, ['smartTableData' => ['headerRows' => [], 'bodyRows' => [], 'columns' => []]]);
        }

        if ($mode === 'pivot') {
            $tableData = $this->buildPivotTable($arrayData, $ptl);
        } else {
            $tableData = $this->buildNormalSmartTable($arrayData, $ptl);
        }

        return array_merge($element, ['smartTableData' => $tableData]);
    }

    /**
     * Resolve array data source for smart table binding.
     */
    private function resolveSmartTableDataSource(string $bind, Product $product, string $language): array
    {
        if ($bind === 'variants') {
            $variants = $product->variants ?? collect();
            return $variants->map(function ($variant) use ($language) {
                $row = [
                    'sku' => $variant->sku ?? '',
                    'name' => $variant->name ?? '',
                    'ean' => $variant->ean ?? '',
                    'status' => $variant->status ?? '',
                ];
                // Varianten-Attribute als flache Felder hinzufügen
                $attrValues = $variant->attributeValues ?? collect();
                foreach ($attrValues as $av) {
                    $attr = $av->attribute;
                    if (!$attr) {
                        continue;
                    }
                    $key = $attr->technical_name;
                    $resolved = $this->elementRenderer->resolveAttributeValue($variant, $attr->id, $language);
                    $parts = [];
                    if ($resolved['value'] !== '') {
                        $parts[] = $resolved['value'];
                    }
                    if ($resolved['unit'] !== '') {
                        $parts[] = $resolved['unit'];
                    }
                    $row[$key] = implode(' ', $parts);
                }
                return $row;
            })->values()->toArray();
        }

        if ($bind === 'relations') {
            $relations = $product->outgoingRelations ?? collect();
            return $relations->map(function ($relation) use ($language) {
                $target = $relation->targetProduct;
                return [
                    'relation_type' => $relation->relationType?->{"name_{$language}"} ?? $relation->relationType?->name_de ?? '',
                    'sku' => $target?->sku ?? '',
                    'name' => $target?->name ?? '',
                    'ean' => $target?->ean ?? '',
                ];
            })->values()->toArray();
        }

        if ($bind === 'attributes') {
            $attrValues = $product->attributeValues ?? collect();
            return $attrValues->map(function ($av) use ($product, $language) {
                $attr = $av->attribute;
                if (!$attr || $attr->is_internal) {
                    return null;
                }
                $resolved = $this->elementRenderer->resolveAttributeValue($product, $attr->id, $language);
                return [
                    'name' => $attr->{"name_{$language}"} ?? $attr->name_de ?? $attr->technical_name,
                    'technical_name' => $attr->technical_name,
                    'value' => $resolved['value'] ?? '',
                    'unit' => $resolved['unit'] ?? '',
                    'group' => $attr->attributeType?->{"name_{$language}"} ?? $attr->attributeType?->name_de ?? '',
                ];
            })->filter()->values()->toArray();
        }

        return [];
    }

    /**
     * Normale Smart Table: Spalten-Definitionen auf Array-Daten anwenden.
     */
    private function buildNormalSmartTable(array $arrayData, array $ptl): array
    {
        $columns = $ptl['columns'] ?? [];
        if (empty($columns)) {
            return ['headerRows' => [], 'bodyRows' => [], 'columns' => []];
        }

        // Flache Spalten extrahieren (Gruppen auflösen)
        $flatColumns = [];
        $headerRows = $this->buildSmartTableHeaderRows($columns);
        $this->flattenColumns($columns, $flatColumns);

        // Datenzeilen aufbauen
        $bodyRows = [];
        foreach ($arrayData as $item) {
            $row = [];
            foreach ($flatColumns as $col) {
                $field = $col['field'] ?? '';
                $value = $item[$field] ?? '';
                $row[] = $this->formatSmartTableValue($value, $col);
            }
            $bodyRows[] = $row;
        }

        return [
            'headerRows' => $headerRows,
            'bodyRows' => $bodyRows,
            'columns' => $flatColumns,
        ];
    }

    /**
     * Header-Zeilen für gruppierte Spalten aufbauen.
     * Gibt ein Array von Zeilen zurück, jede Zeile enthält Zellen mit label, colspan, rowspan.
     */
    private function buildSmartTableHeaderRows(array $columns): array
    {
        $maxDepth = $this->getColumnDepth($columns);
        $rows = [];
        for ($i = 0; $i < $maxDepth; $i++) {
            $rows[] = [];
        }
        $this->collectHeaderCells($columns, $rows, 0, $maxDepth);
        return $rows;
    }

    private function getColumnDepth(array $columns): int
    {
        $max = 1;
        foreach ($columns as $col) {
            if (!empty($col['children'])) {
                $childDepth = 1 + $this->getColumnDepth($col['children']);
                $max = max($max, $childDepth);
            }
        }
        return $max;
    }

    private function collectHeaderCells(array $columns, array &$rows, int $level, int $maxDepth): void
    {
        foreach ($columns as $col) {
            if (!empty($col['children'])) {
                $colspan = $this->countLeafColumns($col['children']);
                $rows[$level][] = [
                    'label' => $col['label'] ?? '',
                    'colspan' => $colspan,
                    'rowspan' => 1,
                ];
                $this->collectHeaderCells($col['children'], $rows, $level + 1, $maxDepth);
            } else {
                $rowspan = $maxDepth - $level;
                $rows[$level][] = [
                    'label' => $col['label'] ?? ($col['field'] ?? ''),
                    'colspan' => 1,
                    'rowspan' => $rowspan,
                ];
            }
        }
    }

    private function countLeafColumns(array $columns): int
    {
        $count = 0;
        foreach ($columns as $col) {
            if (!empty($col['children'])) {
                $count += $this->countLeafColumns($col['children']);
            } else {
                $count++;
            }
        }
        return $count;
    }

    private function flattenColumns(array $columns, array &$flat): void
    {
        foreach ($columns as $col) {
            if (!empty($col['children'])) {
                $this->flattenColumns($col['children'], $flat);
            } else {
                $flat[] = $col;
            }
        }
    }

    /**
     * Pivot-Tabelle: Zeilen-/Spalten-Felder umstrukturieren.
     */
    private function buildPivotTable(array $arrayData, array $ptl): array
    {
        $pivot = $ptl['pivot'] ?? [];
        $rowField = $pivot['rowField'] ?? '';
        $colField = $pivot['colField'] ?? '';
        $valueField = $pivot['valueField'] ?? '';
        $aggregation = $pivot['aggregation'] ?? 'first';

        if (!$rowField || !$colField || !$valueField) {
            return ['headerRows' => [], 'bodyRows' => [], 'columns' => []];
        }

        // Eindeutige Spalten- und Zeilenwerte sammeln
        $colValues = [];
        $rowValues = [];
        $matrix = [];

        foreach ($arrayData as $item) {
            $rv = (string) ($item[$rowField] ?? '');
            $cv = (string) ($item[$colField] ?? '');
            $val = $item[$valueField] ?? '';

            if (!in_array($cv, $colValues)) {
                $colValues[] = $cv;
            }
            if (!in_array($rv, $rowValues)) {
                $rowValues[] = $rv;
            }

            $matrix[$rv][$cv][] = $val;
        }

        // Flat columns für Pivot
        $flatColumns = [['field' => $rowField, 'label' => $rowField, 'align' => 'left']];
        foreach ($colValues as $cv) {
            $flatColumns[] = ['field' => $cv, 'label' => $cv, 'align' => 'right'];
        }

        // Header: eine Zeile
        $headerRow = [];
        foreach ($flatColumns as $col) {
            $headerRow[] = ['label' => $col['label'], 'colspan' => 1, 'rowspan' => 1];
        }

        // Body-Zeilen
        $bodyRows = [];
        foreach ($rowValues as $rv) {
            $row = [$rv];
            foreach ($colValues as $cv) {
                $vals = $matrix[$rv][$cv] ?? [];
                $row[] = $this->aggregateValue($vals, $aggregation);
            }
            $bodyRows[] = $row;
        }

        return [
            'headerRows' => [$headerRow],
            'bodyRows' => $bodyRows,
            'columns' => $flatColumns,
        ];
    }

    private function aggregateValue(array $values, string $aggregation): string
    {
        if (empty($values)) {
            return '';
        }

        return match ($aggregation) {
            'sum' => (string) array_sum(array_map('floatval', $values)),
            'avg' => (string) round(array_sum(array_map('floatval', $values)) / count($values), 2),
            'count' => (string) count($values),
            'min' => (string) min(array_map('floatval', $values)),
            'max' => (string) max(array_map('floatval', $values)),
            default => (string) $values[0], // 'first'
        };
    }

    private function formatSmartTableValue(mixed $value, array $column): string
    {
        $format = $column['format'] ?? 'text';
        if ($value === '' || $value === null) {
            return '';
        }

        return match ($format) {
            'number' => is_numeric($value) ? number_format((float) $value, (int) ($column['decimals'] ?? 0), ',', '.') : (string) $value,
            'currency' => is_numeric($value) ? number_format((float) $value, 2, ',', '.') . ' €' : (string) $value,
            'percent' => is_numeric($value) ? number_format((float) $value, 1, ',', '.') . ' %' : (string) $value,
            default => (string) $value,
        };
    }

    private function getMediaPath($media): string
    {
        $disk = $media->disk ?? 'public';
        $path = $media->file_path ?? '';

        if ($disk === 'public') {
            return storage_path('app/public/' . $path);
        }

        return storage_path('app/' . $path);
    }
}
