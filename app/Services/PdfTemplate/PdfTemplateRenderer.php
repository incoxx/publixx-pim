<?php

declare(strict_types=1);

namespace App\Services\PdfTemplate;

use App\Models\Attribute;
use App\Models\Product;
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
