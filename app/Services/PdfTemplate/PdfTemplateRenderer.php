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
            'image' => $this->resolveImageElement($element, $product),
            'text' => $this->resolveTextElement($element, $product, $language),
            'variant_table' => $this->resolveVariantTableElement($element, $product, $language),
            'relation_table' => $this->resolveRelationTableElement($element, $product, $language),
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
        ]);
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
            if ($col === 'sku') {
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

        // Build rows
        $rows = [];
        foreach ($relations->sortBy('sort_order') as $relation) {
            $target = $relation->targetProduct;
            if (!$target) {
                continue;
            }

            $row = [];
            foreach ($columns as $col) {
                if ($col === 'sku') {
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
                                'Selection', 'Dictionary', 'ValueList' => $attrVal->valueListEntry
                                    ? ($language === 'en' && $attrVal->valueListEntry->display_value_en
                                        ? $attrVal->valueListEntry->display_value_en
                                        : $attrVal->valueListEntry->display_value_de)
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
