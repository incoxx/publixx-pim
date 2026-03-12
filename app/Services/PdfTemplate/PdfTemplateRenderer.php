<?php

declare(strict_types=1);

namespace App\Services\PdfTemplate;

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

        return array_merge($element, ['resolvedImages' => $images]);
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

    private function getMediaPath($media): string
    {
        $disk = $media->disk ?? 'public';
        $path = $media->path ?? '';

        if ($disk === 'public') {
            return storage_path('app/public/' . $path);
        }

        return storage_path('app/' . $path);
    }
}
