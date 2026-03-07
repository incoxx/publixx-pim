<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Attribute;
use App\Models\Product;

class ElementRenderer
{
    /**
     * Resolve placeholders in a text string.
     *
     * Supported placeholders:
     * {date}, {product_type.name}, {hierarchy_node.name}, {hierarchy_node.path},
     * {count}, {group.label}, {group.value}
     */
    public function resolveText(string $template, array $context = []): string
    {
        $replacements = [
            '{date}' => now()->format($context['date_format'] ?? 'd.m.Y'),
            '{datetime}' => now()->format($context['datetime_format'] ?? 'd.m.Y H:i'),
        ];

        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($template, $replacements);
    }

    /**
     * Resolve a base field value from a product.
     */
    public function resolveFieldValue(Product $product, string $field, string $language = 'de'): string
    {
        return match ($field) {
            'sku' => $product->sku ?? '',
            'name' => $product->name ?? '',
            'ean' => $product->ean ?? '',
            'status' => $product->status ?? '',
            'created_at' => $product->created_at?->format('d.m.Y H:i') ?? '',
            'updated_at' => $product->updated_at?->format('d.m.Y H:i') ?? '',
            'product_type' => $product->productType?->{"name_{$language}"} ?? $product->productType?->name_de ?? '',
            'hierarchy_node' => $this->resolveHierarchyPath($product, $language),
            default => '',
        };
    }

    /**
     * Resolve an attribute value from a product.
     *
     * @return array{label: string, value: string, unit: string}
     */
    public function resolveAttributeValue(Product $product, string $attributeId, string $language = 'de'): array
    {
        $attribute = Attribute::find($attributeId);

        if (!$attribute) {
            return ['label' => '', 'value' => '', 'unit' => ''];
        }

        $label = $attribute->{"name_{$language}"} ?? $attribute->name_de ?? $attribute->technical_name ?? '';

        // Composite attributes: aggregate child values with format template
        if ($attribute->data_type === 'Composite') {
            $value = $this->resolveCompositeDisplayValue($product, $attribute, $language);
            return ['label' => $label, 'value' => $value, 'unit' => ''];
        }

        $attributeValue = $product->attributeValues
            ->first(fn ($av) => $av->attribute_id === $attributeId);

        if (!$attributeValue) {
            return ['label' => $label, 'value' => '', 'unit' => ''];
        }

        // Resolve value based on data type
        $value = $this->resolveAttributeDisplayValue($attributeValue, $language);

        $unit = $attributeValue->unit?->abbreviation ?? '';

        return ['label' => $label, 'value' => $value, 'unit' => $unit];
    }

    /**
     * Resolve a composite attribute's display value by aggregating child values.
     */
    private function resolveCompositeDisplayValue(Product $product, Attribute $composite, string $language): string
    {
        $children = $composite->childAttributes()->orderBy('position')->get();

        if ($children->isEmpty()) {
            return '';
        }

        $values = [];
        foreach ($children as $child) {
            $childValue = $product->attributeValues
                ->first(fn ($av) => $av->attribute_id === $child->id);

            $values[] = $childValue ? $this->resolveAttributeDisplayValue($childValue, $language) : '';
        }

        if ($composite->composite_format) {
            $result = $composite->composite_format;
            foreach ($values as $i => $v) {
                $result = str_replace('{' . $i . '}', $v, $result);
            }
            return trim($result);
        }

        $filled = array_filter($values, fn ($v) => $v !== '');
        return implode(' × ', $filled);
    }

    /**
     * Get the display value for an attribute value.
     */
    private function resolveAttributeDisplayValue(mixed $attributeValue, string $language): string
    {
        // Value list entry
        if ($attributeValue->valueListEntry) {
            return $attributeValue->valueListEntry->{"value_{$language}"}
                ?? $attributeValue->valueListEntry->value_de
                ?? '';
        }

        // Boolean
        if ($attributeValue->value_boolean !== null) {
            return $attributeValue->value_boolean ? ($language === 'de' ? 'Ja' : 'Yes') : ($language === 'de' ? 'Nein' : 'No');
        }

        // Number
        if ($attributeValue->value_number !== null) {
            $dec = $language === 'de' ? ',' : '.';
            $thou = $language === 'de' ? '.' : ',';
            return rtrim(rtrim(number_format((float) $attributeValue->value_number, 4, $dec, $thou), '0'), $dec);
        }

        // Date
        if ($attributeValue->value_date !== null) {
            return date('d.m.Y', strtotime($attributeValue->value_date));
        }

        // Text (multilingual)
        if ($attributeValue->value_json !== null && is_array($attributeValue->value_json)) {
            return $attributeValue->value_json[$language] ?? $attributeValue->value_json['de'] ?? '';
        }

        // Simple string
        return $attributeValue->value_string ?? '';
    }

    /**
     * Resolve hierarchy breadcrumb path for a product.
     */
    private function resolveHierarchyPath(Product $product, string $language): string
    {
        $node = $product->masterHierarchyNode;
        if (!$node) {
            return '';
        }

        return $node->{"name_{$language}"} ?? $node->name_de ?? '';
    }

    /**
     * Get the grouping value for a product.
     */
    public function resolveGroupValue(Product $product, string $groupField, string $language = 'de'): string
    {
        if (str_starts_with($groupField, 'attribute:')) {
            $attributeId = substr($groupField, 10);
            $resolved = $this->resolveAttributeValue($product, $attributeId, $language);
            return $resolved['value'] ?: '—';
        }

        return match ($groupField) {
            'product_type' => $product->productType?->{"name_{$language}"} ?? $product->productType?->name_de ?? '—',
            'master_hierarchy_node' => $this->resolveHierarchyPath($product, $language) ?: '—',
            'status' => $product->status ?? '—',
            'none' => '',
            default => '—',
        };
    }
}
