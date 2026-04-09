<?php

declare(strict_types=1);

namespace App\Services\ApiDesigner;

use App\Models\Product;
use App\Services\Report\ElementRenderer;

class JsonWriter
{
    public function __construct(
        private readonly ElementRenderer $elementRenderer,
    ) {}

    /**
     * Build the complete JSON structure from grouped data and template definition.
     *
     * @param array $pagination  Optional keys: total, count, offset, limit, next_url, prev_url
     * @param array $fields      Sparse fieldset: if non-empty, only these JSON-Keys per product
     */
    public function build(array $grouped, array $templateJson, string $language, array $pagination = [], array $fields = []): array
    {
        $count = $pagination['count'] ?? $this->countProducts($grouped);
        $total = $pagination['total'] ?? $count;

        $result = [
            'generated_at' => now()->toIso8601String(),
            'total'        => $total,
            'count'        => $count,
        ];

        if (isset($pagination['offset']) && $pagination['offset'] > 0) {
            $result['offset'] = $pagination['offset'];
        }
        if (isset($pagination['limit'])) {
            $result['limit'] = $pagination['limit'];
        }
        if (!empty($pagination['next_url'])) {
            $result['next_url'] = $pagination['next_url'];
        }
        if (!empty($pagination['prev_url'])) {
            $result['prev_url'] = $pagination['prev_url'];
        }

        $result['groups'] = $this->buildGroups($grouped, $language, $fields);

        return $result;
    }

    /**
     * Build JSON for a streamed response (outputs JSON string incrementally).
     *
     * @param array $pagination  Optional keys: total, count, offset, limit, next_url, prev_url
     * @param array $fields      Sparse fieldset: if non-empty, only these JSON-Keys per product
     */
    public function buildString(array $grouped, array $templateJson, string $language, array $pagination = [], array $fields = []): string
    {
        $data = $this->build($grouped, $templateJson, $language, $pagination, $fields);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function buildGroups(array $grouped, string $language, array $fields = []): array
    {
        $result = [];

        foreach ($grouped as $group) {
            $definition = $group['definition'] ?? [];
            $entry = [];

            // Group meta
            if (!empty($group['value'])) {
                $entry['field'] = $definition['field'] ?? null;
                $entry['value'] = $group['value'];
            }

            // Header elements → meta object
            $headerElements = $definition['header']['elements'] ?? [];
            if (!empty($headerElements)) {
                $entry['header'] = $this->buildSectionMeta($headerElements, $group, $language);
            }

            // Products (detail section)
            if (!empty($group['products'])) {
                $detailElements = $definition['detail']['elements'] ?? [];
                $entry['products'] = $this->buildProducts($group['products'], $detailElements, $language, $fields);
            }

            // Footer elements → summary object
            $footerElements = $definition['footer']['elements'] ?? [];
            if (!empty($footerElements)) {
                $entry['footer'] = $this->buildSectionMeta($footerElements, $group, $language);
            }

            // Nested subgroups
            if (!empty($group['subgroups'])) {
                $entry['groups'] = $this->buildGroups($group['subgroups'], $language, $fields);
            }

            $entry['count'] = $group['count'] ?? 0;

            $result[] = $entry;
        }

        return $result;
    }

    private function buildProducts(array $products, array $elements, string $language, array $fields = []): array
    {
        $result = [];

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $productData = [];

            foreach ($elements as $element) {
                $key = $element['jsonKey'] ?? $element['field'] ?? $element['attributeId'] ?? null;
                if (!$key) {
                    continue;
                }
                // Sparse fieldset: überspringen wenn Key nicht in der Auswahl
                if (!empty($fields) && !in_array($key, $fields, true)) {
                    continue;
                }

                $value = $this->resolveElementValue($element, $product, $language);
                $productData[$key] = $value;
            }

            // Wenn keine Elements definiert → Fallback-Felder (gefiltert durch fieldset)
            if (empty($elements)) {
                $defaults = ['sku' => $product->sku ?? '', 'name' => $product->name ?? '', 'status' => $product->status ?? ''];
                $productData = empty($fields) ? $defaults : array_intersect_key($defaults, array_flip($fields));
            }

            $result[] = $productData;
        }

        return $result;
    }

    private function resolveElementValue(array $element, Product $product, string $language): mixed
    {
        $type = $element['type'] ?? 'field';

        if ($type === 'attribute') {
            $resolved = $this->elementRenderer->resolveAttributeValue(
                $product,
                $element['attributeId'],
                $language,
            );
            $value = $resolved['value'];

            // Include unit if configured
            if (!empty($element['includeUnit']) && !empty($resolved['unit'])) {
                $value = "{$value} {$resolved['unit']}";
            }

            return $this->castValue($value, $element['dataType'] ?? 'string');
        }

        if ($type === 'field') {
            $value = $this->elementRenderer->resolveFieldValue($product, $element['field'], $language);
            return $this->castValue($value, $element['dataType'] ?? 'string');
        }

        if ($type === 'text') {
            return $element['content'] ?? '';
        }

        return null;
    }

    private function castValue(mixed $value, string $dataType): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return match ($dataType) {
            'number' => is_numeric($value) ? (float) $value : $value,
            'integer' => is_numeric($value) ? (int) $value : $value,
            'boolean' => (bool) $value,
            default => (string) $value,
        };
    }

    private function buildSectionMeta(array $elements, array $group, string $language): array
    {
        $meta = [];

        foreach ($elements as $element) {
            $key = $element['jsonKey'] ?? $element['field'] ?? $element['type'] ?? 'value';
            $type = $element['type'] ?? 'text';

            if ($type === 'text') {
                $content = $element['content'] ?? '';
                $meta[$key] = $this->elementRenderer->resolveText($content, [
                    'count' => $group['count'] ?? 0,
                    'group.label' => $group['label'] ?? '',
                    'group.value' => $group['value'] ?? '',
                ]);
            } elseif ($type === 'counter') {
                $meta[$key] = $group['count'] ?? 0;
            } else {
                $meta[$key] = $element['content'] ?? '';
            }
        }

        return $meta;
    }

    private function countProducts(array $grouped): int
    {
        $count = 0;
        foreach ($grouped as $group) {
            $count += count($group['products'] ?? []);
            if (!empty($group['subgroups'])) {
                $count += $this->countProducts($group['subgroups']);
            }
        }
        return $count;
    }
}
