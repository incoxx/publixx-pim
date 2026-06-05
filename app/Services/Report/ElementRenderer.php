<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use App\Services\CompositeFormatResolver;
use Illuminate\Support\Facades\Storage;

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
            return ['label' => $label, 'value' => $value, 'unit' => '', 'values' => $value !== '' ? [$value] : []];
        }

        // Multipliable einfache Attribute: alle Werte sammeln
        if ($attribute->is_multipliable) {
            $allValues = $product->attributeValues
                ->filter(fn ($av) => $av->attribute_id === $attributeId)
                ->sortBy('multiplied_index');

            if ($allValues->isEmpty()) {
                return ['label' => $label, 'value' => '', 'unit' => '', 'values' => []];
            }

            $displayValues = $allValues
                ->map(fn ($av) => $this->resolveAttributeDisplayValue($av, $language))
                ->filter(fn ($v) => $v !== '')
                ->values()
                ->all();

            $unit = $allValues->first()->unit?->abbreviation ?? '';

            return [
                'label' => $label,
                'value' => implode(', ', $displayValues),
                'unit' => $unit,
                'values' => $displayValues,
            ];
        }

        $attributeValue = $product->attributeValues
            ->first(fn ($av) => $av->attribute_id === $attributeId);

        if (!$attributeValue) {
            return ['label' => $label, 'value' => '', 'unit' => '', 'values' => []];
        }

        // Resolve value based on data type
        $value = $this->resolveAttributeDisplayValue($attributeValue, $language);

        $unit = $attributeValue->unit?->abbreviation ?? '';

        return ['label' => $label, 'value' => $value, 'unit' => $unit, 'values' => $value !== '' ? [$value] : []];
    }

    /**
     * Resolve a composite attribute's display value by aggregating child values.
     */
    private function resolveCompositeDisplayValue(Product $product, Attribute $composite, string $language): string
    {
        // If composite has an expression, show the computed value
        if ($composite->composite_expression) {
            $computedValue = $product->attributeValues
                ->first(fn ($av) => $av->attribute_id === $composite->id);

            if ($computedValue?->value_number !== null) {
                return (string) (float) $computedValue->value_number;
            }

            return '';
        }

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
            return CompositeFormatResolver::resolve(
                $composite->composite_format,
                $children->all(),
                $values
            );
        }

        $filled = array_filter($values, fn ($v) => $v !== '');
        return implode(' × ', $filled);
    }

    /**
     * Get the display value for an attribute value.
     */
    private function resolveAttributeDisplayValue(mixed $attributeValue, string $language): string
    {
        // Dictionary entry
        if ($attributeValue->dictionaryEntry) {
            return ($language === 'en' && $attributeValue->dictionaryEntry->short_text_en
                ? $attributeValue->dictionaryEntry->short_text_en
                : $attributeValue->dictionaryEntry->short_text_de) ?? '';
        }

        // Value list entry (Selection)
        if ($attributeValue->valueListEntry) {
            return ($language === 'en' && $attributeValue->valueListEntry->display_value_en
                ? $attributeValue->valueListEntry->display_value_en
                : $attributeValue->valueListEntry->display_value_de) ?? '';
        }

        // Boolean (Flag)
        if ($attributeValue->value_flag !== null) {
            return $attributeValue->value_flag ? ($language === 'de' ? 'Ja' : 'Yes') : ($language === 'de' ? 'Nein' : 'No');
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
     * Löst einen Preis eines bestimmten Preistyps auf.
     *
     * @return array{amount: float|null, currency: string|null}
     */
    public function resolvePriceValue(Product $product, string $priceTypeId): array
    {
        $price = $product->prices
            ->where('price_type_id', $priceTypeId)
            ->first();

        if (!$price) {
            return ['amount' => null, 'currency' => null];
        }

        return [
            'amount'   => $price->amount !== null ? (float) $price->amount : null,
            'currency' => $price->currency ?? 'EUR',
        ];
    }

    /**
     * Löst Medien eines bestimmten Verwendungstyps auf.
     *
     * mode 'url'   → primäre URL als String (oder null)
     * mode 'array' → alle Medien als Array von Objekten
     *
     * @return string|null|list<array{url: string|null, alt: string|null, mime_type: string|null, width: int|null, height: int|null}>
     */
    public function resolveMediaValue(Product $product, string $usageTypeId, string $mode = 'url', string $language = 'de'): mixed
    {
        $assignments = $product->mediaAssignments
            ->where('usage_type_id', $usageTypeId)
            ->sortBy('sort_order')
            ->values();

        if ($assignments->isEmpty()) {
            return $mode === 'array' ? [] : null;
        }

        if ($mode === 'array') {
            return $assignments->map(fn (ProductMediaAssignment $a) => $this->buildMediaItem($a, $language))->all();
        }

        // mode 'url' → primäres Medium (is_primary) oder erstes
        $primary = $assignments->firstWhere('is_primary', true) ?? $assignments->first();
        return $primary ? $this->buildMediaUrl($primary) : null;
    }

    private function buildMediaUrl(ProductMediaAssignment $assignment): ?string
    {
        $path = $assignment->media?->file_path;
        return $path ? Storage::url($path) : null;
    }

    private function buildMediaItem(ProductMediaAssignment $assignment, string $language): array
    {
        $media = $assignment->media;
        return [
            'url'       => $media?->file_path ? Storage::url($media->file_path) : null,
            'alt'       => $media?->{"alt_text_{$language}"} ?? $media?->alt_text_de ?? $media?->{"title_{$language}"} ?? $media?->title_de ?? null,
            'mime_type' => $media?->mime_type ?? null,
            'width'     => $media?->width,
            'height'    => $media?->height,
        ];
    }

    /**
     * Löst ausgehende Produktbeziehungen eines Beziehungstyps auf.
     *
     * @return list<array{sku: string, name: string}>
     */
    public function resolveRelationValue(Product $product, string $relationTypeId, string $language = 'de'): array
    {
        return $product->outgoingRelations
            ->where('relation_type_id', $relationTypeId)
            ->sortBy('sort_order')
            ->map(fn ($rel) => [
                'sku'  => $rel->targetProduct?->sku ?? '',
                'name' => $rel->targetProduct?->{"name_{$language}"} ?? $rel->targetProduct?->name ?? '',
            ])
            ->values()
            ->all();
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
