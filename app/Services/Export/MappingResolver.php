<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\PublixxExportMapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolves export mapping rules against a product.
 *
 * Each rule has: source (where to read), target (where to write), type (how to transform).
 * Supports dot-notation targets for nested JSON output.
 */
class MappingResolver
{
    /**
     * Resolve all mapping rules for a product and return a flat key=>value map.
     *
     * @param  array  $rules     Mapping rules from PublixxExportMapping->mapping_rules['rules']
     * @param  Product $product  Loaded product (with relations as needed)
     * @param  array  $languages e.g. ['de', 'en']
     * @param  array  $options   Additional context (attributeValues, media, prices, variants, relations)
     * @return array  Flat key=>value pairs (targets may contain dots for nesting)
     */
    public function resolve(array $rules, Product $product, array $languages = ['de'], array $options = []): array
    {
        $result = [];
        $primaryLang = $languages[0] ?? 'de';
        $additionalLangs = array_slice($languages, 1);

        foreach ($rules as $rule) {
            $source = $rule['source'] ?? '';
            $target = $rule['target'] ?? '';
            $type = $rule['type'] ?? 'text';

            if ($target === '' || $source === '') {
                continue;
            }

            $resolved = $this->resolveRule($source, $type, $product, $primaryLang, $options);
            $result[$target] = $resolved;

            // i18n: additional languages get suffix fields
            if ($this->isTranslatableType($type) && count($additionalLangs) > 0) {
                foreach ($additionalLangs as $lang) {
                    $langValue = $this->resolveRule($source, $type, $product, $lang, $options);
                    if ($langValue !== null && $langValue !== $resolved) {
                        $result[$target . '_' . $lang] = $langValue;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Resolve a single mapping rule.
     */
    public function resolveRule(string $source, string $type, Product $product, string $language, array $options = []): mixed
    {
        return match ($type) {
            'text' => $this->resolveText($source, $product, $language, $options),
            'unit_value' => $this->resolveUnitValue($source, $product, $language, $options),
            'media_url' => $this->resolveMediaUrl($source, $product, $options),
            'media_array' => $this->resolveMediaArray($source, $product, $options),
            'price' => $this->resolvePrice($source, $product, $options),
            'variant_array' => $this->resolveVariantArray($product, $options),
            'relation_array' => $this->resolveRelationArray($source, $product, $options),
            'group' => $this->resolveGroup($source, $product, $language, $options),
            default => null,
        };
    }

    /**
     * text: attribute:tech_name → string value
     */
    protected function resolveText(string $source, Product $product, string $language, array $options): ?string
    {
        $attrValue = $this->findAttributeValue($source, $product, $language, $options);

        if ($attrValue === null) {
            return null;
        }

        // Prefer resolved value from AttributeValueResolver if available
        if (isset($attrValue['resolved_value'])) {
            return (string) $attrValue['resolved_value'];
        }

        // Selection → display value
        if ($attrValue instanceof ProductAttributeValue && $attrValue->value_selection_id !== null) {
            $entry = $attrValue->valueListEntry;
            if ($entry) {
                $field = "display_value_{$language}";
                return $entry->{$field} ?? $entry->display_value_de ?? $entry->technical_name;
            }
        }

        return $attrValue->value_string
            ?? ($attrValue->value_number !== null ? (string) $attrValue->value_number : null)
            ?? ($attrValue->value_flag !== null ? ($attrValue->value_flag ? 'true' : 'false') : null)
            ?? ($attrValue->value_date !== null ? (string) $attrValue->value_date : null);
    }

    /**
     * unit_value: attribute:tech_name → { value: x, unit: "kg" }
     */
    protected function resolveUnitValue(string $source, Product $product, string $language, array $options): ?array
    {
        $attrValue = $this->findAttributeValue($source, $product, $language, $options);

        if ($attrValue === null || $attrValue->value_number === null) {
            return null;
        }

        $unit = null;
        if ($attrValue->unit_id && $attrValue->unit) {
            $unit = $attrValue->unit->abbreviation;
        }

        return [
            'value' => (float) $attrValue->value_number,
            'unit' => $unit,
        ];
    }

    /**
     * media_url: media:usage_type → single URL
     */
    protected function resolveMediaUrl(string $source, Product $product, array $options): ?string
    {
        $usageType = $this->extractSourceParam($source);
        $mediaAssignments = $options['media'] ?? $product->mediaAssignments ?? collect();

        $assignment = $mediaAssignments
            ->filter(fn ($a) => $a->usageType?->technical_name === $usageType)
            ->sortBy('sort_order')
            ->first();

        if ($assignment === null) {
            return null;
        }

        $media = $assignment->media ?? null;
        return $media?->file_path;
    }

    /**
     * media_array: media:usage_type → [url1, url2, ...]
     */
    protected function resolveMediaArray(string $source, Product $product, array $options): array
    {
        $usageType = $this->extractSourceParam($source);
        $mediaAssignments = $options['media'] ?? $product->mediaAssignments ?? collect();

        return $mediaAssignments
            ->filter(fn ($a) => $a->usageType?->technical_name === $usageType)
            ->sortBy('sort_order')
            ->map(fn ($a) => $a->media?->file_path)
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * price: prices:price_type → decimal amount
     */
    protected function resolvePrice(string $source, Product $product, array $options): ?float
    {
        $priceTypeName = $this->extractSourceParam($source);
        $prices = $options['prices'] ?? $product->prices ?? collect();

        $price = $prices->first(function ($p) use ($priceTypeName) {
            $type = $p->priceType ?? null;
            return $type && $type->technical_name === $priceTypeName;
        });

        return $price ? (float) $price->amount : null;
    }

    /**
     * variant_array: variants → [{ sku, name, ... }, ...]
     */
    protected function resolveVariantArray(Product $product, array $options): array
    {
        $variants = $options['variants'] ?? $product->variants ?? collect();

        return $variants->map(function ($variant) {
            $data = [
                'sku' => $variant->sku,
                'name' => $variant->name,
            ];

            // Include variant prices if loaded
            if ($variant->relationLoaded('prices') && $variant->prices->isNotEmpty()) {
                $listPrice = $variant->prices->first();
                if ($listPrice) {
                    $data['preis'] = (float) $listPrice->amount;
                }
            }

            return $data;
        })->values()->toArray();
    }

    /**
     * relation_array: relations:rel_type → [{ sku, name, ... }, ...]
     */
    protected function resolveRelationArray(string $source, Product $product, array $options): array
    {
        $relTypeName = $this->extractSourceParam($source);
        $relations = $options['relations'] ?? $product->outgoingRelations ?? collect();

        return $relations
            ->filter(function ($rel) use ($relTypeName) {
                $type = $rel->relationType ?? null;
                return $type && $type->technical_name === $relTypeName;
            })
            ->map(function ($rel) {
                $target = $rel->targetProduct;
                if (!$target) {
                    return null;
                }
                $data = [
                    'sku' => $target->sku,
                    'name' => $target->name,
                ];

                // Include primary image if loaded
                if ($target->relationLoaded('mediaAssignments')) {
                    $primary = $target->mediaAssignments
                        ->filter(fn ($a) => $a->usageType?->technical_name === 'teaser')
                        ->sortBy('sort_order')
                        ->first();
                    if ($primary && $primary->media) {
                        $data['image'] = $primary->media->file_path;
                    }
                }

                return $data;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * group: collection:name → grouped attributes as nested object
     */
    protected function resolveGroup(string $source, Product $product, string $language, array $options): ?array
    {
        $collectionName = $this->extractSourceParam($source);
        $attributeAssignments = $options['attributeAssignments'] ?? collect();

        // Filter assignments by collection name
        $grouped = $attributeAssignments
            ->where('collection_name', $collectionName)
            ->sortBy('attribute_sort');

        if ($grouped->isEmpty()) {
            return null;
        }

        $result = [];
        foreach ($grouped as $assignment) {
            $attribute = $assignment->attribute ?? null;
            if (!$attribute) {
                continue;
            }

            $techName = $attribute->technical_name;
            // Find the attribute value for this product
            $attrValue = $this->findAttributeValueByTechName($techName, $product, $language, $options);

            if ($attrValue === null) {
                continue;
            }

            $key = $this->attributeToDisplayKey($attribute, $language);

            if ($attrValue->unit_id && $attrValue->value_number !== null) {
                $result[$key] = [
                    'value' => (float) $attrValue->value_number,
                    'unit' => $attrValue->unit?->abbreviation,
                ];
            } elseif ($attrValue->value_number !== null) {
                $result[$key] = (float) $attrValue->value_number;
            } elseif ($attrValue->value_string !== null) {
                $result[$key] = $attrValue->value_string;
            } elseif ($attrValue->value_flag !== null) {
                $result[$key] = $attrValue->value_flag;
            }
        }

        return !empty($result) ? $result : null;
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Extract parameter from source string. e.g. "attribute:product-name-dict" → "product-name-dict"
     */
    protected function extractSourceParam(string $source): string
    {
        $parts = explode(':', $source, 2);
        return $parts[1] ?? $parts[0];
    }

    /**
     * Extract the source prefix. e.g. "attribute:product-name-dict" → "attribute"
     */
    protected function extractSourcePrefix(string $source): string
    {
        return explode(':', $source, 2)[0];
    }

    /**
     * Find an attribute value from the product by source definition.
     */
    protected function findAttributeValue(string $source, Product $product, string $language, array $options): ?ProductAttributeValue
    {
        $prefix = $this->extractSourcePrefix($source);

        if ($prefix !== 'attribute') {
            return null;
        }

        $techName = $this->extractSourceParam($source);
        return $this->findAttributeValueByTechName($techName, $product, $language, $options);
    }

    /**
     * Find attribute value by technical name, using AttributeValueResolver if available.
     */
    protected function findAttributeValueByTechName(string $techName, Product $product, string $language, array $options): ?ProductAttributeValue
    {
        // If resolved values from Inheritance Agent are provided, use them
        if (isset($options['resolvedValues'][$techName][$language])) {
            return $options['resolvedValues'][$techName][$language];
        }
        if (isset($options['resolvedValues'][$techName][null])) {
            return $options['resolvedValues'][$techName][null];
        }

        // Fallback: search loaded attributeValues relation
        $values = $options['attributeValues'] ?? $product->attributeValues ?? collect();

        return $values->first(function ($val) use ($techName, $language) {
            $attr = $val->attribute ?? null;
            if (!$attr || $attr->technical_name !== $techName) {
                return false;
            }
            // Match language: exact or null (language-independent)
            return $val->language === $language || $val->language === null;
        });
    }

    /**
     * Convert attribute to a display key for group output.
     */
    protected function attributeToDisplayKey(Attribute $attribute, string $language): string
    {
        $nameField = "name_{$language}";
        $name = $attribute->{$nameField} ?? $attribute->name_de ?? $attribute->technical_name;

        // Convert to camelCase-ish key
        return Str::camel(Str::slug($name, '_'));
    }

    /**
     * Whether a mapping type supports i18n suffix fields.
     */
    protected function isTranslatableType(string $type): bool
    {
        return in_array($type, ['text', 'unit_value', 'group'], true);
    }
}
