<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Attribute;
use App\Models\ProductAttributeValue;
use App\Services\Attributes\AttributeValuePresenter;
use App\Services\CompositeFormatResolver;
use App\Services\Media\PrimaryImageResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $this->additional['lang'] ?? $request->query('lang', 'de');
        $breadcrumb = $this->additional['breadcrumb'] ?? [];
        $allowedAttributeIds = $this->additional['allowed_attribute_ids'] ?? null;

        $name = $this->resource->name;
        $description = $this->resource->searchIndex?->description_de;

        // Titelbild (gleiche Fallback-Kette wie /Produkte und /Medien) an erste Stelle
        // der Galerie sortieren, damit die "Hauptansicht" überall dasselbe Bild zeigt.
        $primaryMedia = PrimaryImageResolver::resolveFromCollection($this->resource->media);

        $media = $this->resource->media
            ->sortBy(fn ($m) => $primaryMedia && $m->id === $primaryMedia->id ? 0 : 1)
            ->values()
            ->map(function ($m) use ($lang) {
                return [
                    'url' => url('api/v1/catalog/media/' . rawurlencode($m->file_name)),
                    'alt' => $lang === 'en' && $m->alt_text_en ? $m->alt_text_en : $m->alt_text_de,
                    'is_primary' => (bool) $m->pivot->is_primary,
                    'media_type' => $m->media_type,
                    'mime_type' => $m->mime_type,
                    'file_name' => $m->file_name,
                    'description' => $lang === 'en' && $m->description_en ? $m->description_en : ($m->description_de ?? ''),
                ];
            })->values();

        $prices = $this->resource->prices->map(function ($p) use ($lang) {
            $typeName = null;
            if ($p->priceType) {
                $typeName = $lang === 'en' && $p->priceType->name_en
                    ? $p->priceType->name_en
                    : $p->priceType->name_de;
            }

            return [
                'amount' => $p->amount,
                'currency' => $p->currency,
                'type_id' => $p->price_type_id,
                'type_name' => $typeName,
            ];
        })->values();

        // Build attributes array from EAV values — exclude internal attributes
        // Group by attribute_id to merge multipliable values into a single entry
        $grouped = $this->resource->attributeValues
            ->sortBy('multiplied_index')
            ->groupBy('attribute_id');

        $attributes = collect();

        foreach ($grouped as $attributeId => $values) {
            $firstValue = $values->first();
            $attr = $firstValue->attribute;
            if (!$attr || $attr->is_internal) {
                continue;
            }

            // Filter by attribute view if configured — but always include link types
            $isLinkType = in_array($attr->data_type, ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink']);
            if ($allowedAttributeIds !== null && !in_array($attr->id, $allowedAttributeIds) && !$isLinkType) {
                continue;
            }

            $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;

            // Multipliable einfache Attribute: Werte zu einem Eintrag zusammenfassen
            if ($attr->is_multipliable && $attr->data_type !== 'Composite' && $values->count() > 1) {
                $displayValues = $values->sortBy('multiplied_index')
                    ->map(fn ($av) => $this->resolveAttributeDisplayValue($av, $attr, $lang))
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->values()
                    ->all();

                if (empty($displayValues)) {
                    continue;
                }

                $unit = $firstValue->unit?->abbreviation;

                $attributes->push([
                    'attribute_id' => $attr->id,
                    'technical_name' => $attr->technical_name,
                    'label' => $label,
                    'value' => implode(', ', $displayValues),
                    'values' => $displayValues,
                    'unit' => $unit,
                    'data_type' => $attr->data_type,
                    'parent_attribute_id' => $attr->parent_attribute_id,
                    'composite_format' => null,
                    'group_id' => $attr->attribute_type_id,
                ]);
                continue;
            }

            // Nicht-multipliable oder Einzelwert: bisherige Logik pro Value
            foreach ($values as $attrValue) {
                $displayValue = $this->resolveAttributeDisplayValue($attrValue, $attr, $lang);

                $linkData = null;
                if ($isLinkType && $attrValue->value_string) {
                    $linkData = json_decode($attrValue->value_string, true);
                    if (!is_array($linkData) || empty($linkData['url'])) {
                        $linkData = null;
                    }
                    if (($displayValue === null || $displayValue === '') && $linkData) {
                        $displayValue = $linkData['title'] ?? $linkData['url'] ?? null;
                    }
                }

                if ($displayValue === null || $displayValue === '') {
                    continue;
                }

                $unit = $attrValue->unit?->abbreviation;

                $entry = [
                    'attribute_id' => $attr->id,
                    'technical_name' => $attr->technical_name,
                    'label' => $label,
                    'value' => $displayValue,
                    'unit' => $unit,
                    'data_type' => $attr->data_type,
                    'parent_attribute_id' => $attr->parent_attribute_id,
                    'composite_format' => $attr->data_type === 'Composite' ? $attr->composite_format : null,
                    'group_id' => $attr->attribute_type_id,
                ];

                if ($linkData) {
                    $entry['link_data'] = $linkData;
                }

                // Für Referenz-Typen: strukturierte reference_data mitliefern (analog link_data).
                if (in_array($attr->data_type, Attribute::REFERENCE_TYPES, true)) {
                    $referenceData = (new AttributeValuePresenter())->referenceData($attrValue, $lang);
                    if ($referenceData) {
                        $entry['reference_data'] = $referenceData;
                    }
                }

                $attributes->push($entry);
            }
        }

        // Sort by attribute position (use already-loaded attribute models)
        $positionMap = $this->resource->attributeValues
            ->mapWithKeys(fn ($av) => [$av->attribute_id => $av->attribute?->position ?? 999])
            ->all();
        $attributes = $attributes->sortBy(fn ($e) => $positionMap[$e['attribute_id']] ?? 999)->values();

        // Add composite parent entries (they have no own value but aggregate children)
        $attributes = $this->injectCompositeParents($attributes, $lang, $allowedAttributeIds);

        // Build variants with their variant attribute values
        $variants = $this->buildVariants($lang);

        // Build relations (filtered by configured types)
        $relations = $this->buildRelations($lang);

        $descriptionAttributes = $this->additional['description_attributes'] ?? [];

        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'ean' => $this->resource->ean,
            'name' => $name,
            'description' => $description,
            'description_attributes' => $descriptionAttributes,
            'product_type' => $this->resource->searchIndex?->product_type,
            'category_breadcrumb' => $breadcrumb,
            'media' => $media,
            'prices' => $prices,
            'attributes' => $attributes,
            'variants' => $variants,
            'relations' => $relations,
        ];
    }

    private function buildVariants(string $lang): array
    {
        $product = $this->resource;

        if (!$product->relationLoaded('variants') || $product->variants->isEmpty()) {
            return [];
        }

        // Get variant attributes (non-internal, active)
        $variantAttributes = Attribute::where('is_variant_attribute', true)
            ->where('is_internal', false)
            ->where('status', 'active')
            ->orderBy('position')
            ->get();

        // Pre-load attribute values for all variants
        $variantIds = $product->variants->pluck('id');
        $allVariantValues = ProductAttributeValue::whereIn('product_id', $variantIds)
            ->whereIn('attribute_id', $variantAttributes->pluck('id'))
            ->with(['attribute', 'valueListEntry', 'dictionaryEntry', 'unit'])
            ->get()
            ->groupBy('product_id');

        return $product->variants->map(function ($variant) use ($variantAttributes, $allVariantValues, $lang) {
            $variantAttrValues = $allVariantValues->get($variant->id, collect());

            $variantAttrsOutput = $variantAttributes->map(function ($attr) use ($variantAttrValues, $lang) {
                $attrValue = $variantAttrValues->firstWhere('attribute_id', $attr->id);
                $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;

                return [
                    'label' => $label,
                    'value' => $attrValue ? $this->resolveAttributeDisplayValue($attrValue, $attr, $lang) : null,
                    'unit' => $attrValue?->unit?->abbreviation,
                ];
            })->values()->toArray();

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'ean' => $variant->ean,
                'name' => $variant->name,
                'status' => $variant->status,
                'variant_attributes' => $variantAttrsOutput,
            ];
        })->values()->toArray();
    }

    private function buildRelations(string $lang): array
    {
        try {
            $product = $this->resource;

            if (!$product->relationLoaded('outgoingRelations') || $product->outgoingRelations->isEmpty()) {
                return [];
            }

            // Pre-fetch media for all target products in a single query
            $targetIds = $product->outgoingRelations
                ->map(fn ($r) => $r->targetProduct?->id)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $mediaMap = [];
            if (!empty($targetIds)) {
                $productsWithMedia = \App\Models\Product::whereIn('id', $targetIds)
                    ->with('media')
                    ->get();

                foreach ($productsWithMedia as $p) {
                    $primary = PrimaryImageResolver::resolveFromCollection($p->media);
                    if ($primary) {
                        $mediaMap[$p->id] = url('api/v1/catalog/media/' . rawurlencode($primary->file_name));
                    }
                }
            }

            return $product->outgoingRelations->map(function ($relation) use ($lang, $mediaMap) {
                $target = $relation->targetProduct;
                if (!$target || $target->status !== 'active') {
                    return null;
                }

                $typeName = $relation->relationType
                    ? ($lang === 'en' && $relation->relationType->name_en
                        ? $relation->relationType->name_en
                        : $relation->relationType->name_de)
                    : null;

                return [
                    'target_product_id' => $target->id,
                    'sku' => $target->sku,
                    'name' => $target->name,
                    'image_url' => $mediaMap[$target->id] ?? null,
                    'relation_type' => $typeName,
                    'relation_type_id' => $relation->relation_type_id,
                ];
            })->filter()->values()->toArray();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('buildRelations failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Inject composite parent entries for any child attributes present in the list.
     * Handles multipliable composites by building formatted values per instance.
     */
    private function injectCompositeParents($attributes, string $lang, ?array $allowedAttributeIds)
    {
        // Find parent_attribute_ids that are referenced but not present
        $existingIds = $attributes->pluck('attribute_id')->toArray();
        $parentIds = $attributes->pluck('parent_attribute_id')->filter()->unique()->values();
        $missingParentIds = $parentIds->diff($existingIds);

        if ($missingParentIds->isEmpty()) {
            return $attributes;
        }

        $composites = Attribute::whereIn('id', $missingParentIds)
            ->where('data_type', 'Composite')
            ->get();

        // Pre-load raw attribute values for multipliable composites (grouped by attribute_id)
        $rawValues = $this->resource->attributeValues
            ->whereIn('attribute.parent_attribute_id', $composites->pluck('id')->all())
            ->groupBy('attribute_id');

        $newEntries = [];
        foreach ($composites as $composite) {
            if ($allowedAttributeIds !== null && !in_array($composite->id, $allowedAttributeIds)) {
                continue;
            }

            $label = $lang === 'en' && $composite->name_en ? $composite->name_en : $composite->name_de;

            $childModels = Attribute::where('parent_attribute_id', $composite->id)
                ->orderBy('position')
                ->get();

            // Detect multipliable: check if any child has multiple values (multiplied_index)
            $childIds = $childModels->pluck('id')->all();
            $hasMultipleInstances = false;
            $indices = collect([0]); // Default: single instance at index 0

            foreach ($childIds as $childId) {
                $childRawValues = $rawValues->get($childId, collect());
                if ($childRawValues->count() > 1) {
                    $hasMultipleInstances = true;
                }
                $childRawValues->each(function ($v) use (&$indices) {
                    $indices->push($v->multiplied_index ?? 0);
                });
            }
            $indices = $indices->unique()->sort()->values();

            if ($hasMultipleInstances && $indices->count() > 1) {
                // Vermehrbares Composite: Wert pro Instanz berechnen und zusammenführen
                $instanceValues = [];
                foreach ($indices as $idx) {
                    $values = [];
                    foreach ($childModels as $childModel) {
                        $childRawValues = $rawValues->get($childModel->id, collect());
                        $av = $childRawValues->first(fn ($v) => ($v->multiplied_index ?? 0) === $idx);
                        $values[] = $av ? ($this->resolveAttributeDisplayValue($av, $childModel, $lang) ?? '') : '';
                    }

                    $formatted = null;
                    if ($composite->composite_format) {
                        $formatted = CompositeFormatResolver::resolve(
                            $composite->composite_format,
                            $childModels->all(),
                            $values
                        ) ?: null;
                    } else {
                        $filled = array_filter($values, fn ($v) => $v !== '');
                        $formatted = !empty($filled) ? implode(' × ', $filled) : null;
                    }

                    if ($formatted) {
                        $instanceValues[] = $formatted;
                    }
                }

                $formattedValue = !empty($instanceValues) ? implode("\n", $instanceValues) : null;
            } else {
                // Einfaches Composite: einzelne Instanz
                $childResponseEntries = $attributes->where('parent_attribute_id', $composite->id);
                $values = [];
                foreach ($childModels as $childModel) {
                    $entry = $childResponseEntries->firstWhere('attribute_id', $childModel->id);
                    $values[] = $entry ? ((string) ($entry['value'] ?? '')) : '';
                }
                $formattedValue = null;

                if ($composite->composite_format) {
                    $formattedValue = CompositeFormatResolver::resolve(
                        $composite->composite_format,
                        $childModels->all(),
                        $values
                    ) ?: null;
                } else {
                    $filled = array_filter($values, fn ($v) => $v !== '');
                    $formattedValue = !empty($filled) ? implode(' × ', $filled) : null;
                }
            }

            $newEntries[] = [
                'attribute_id' => $composite->id,
                'label' => $label,
                'value' => $formattedValue,
                'unit' => null,
                'data_type' => 'Composite',
                'parent_attribute_id' => null,
                'composite_format' => $composite->composite_format,
            ];
        }

        if (empty($newEntries)) {
            return $attributes;
        }

        return collect(array_merge($newEntries, $attributes->toArray()))->values();
    }

    private function resolveAttributeDisplayValue(ProductAttributeValue $attrValue, Attribute $attr, string $lang): ?string
    {
        // Referenz-/Mehrfach-Typen zentral auflösen.
        $presenter = new AttributeValuePresenter();
        if ($presenter->handles($attr->data_type)) {
            return $presenter->displayValue($attrValue, $lang);
        }

        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.') : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null ? ($attrValue->value_flag ? ($lang === 'en' ? 'Yes' : 'Ja') : ($lang === 'en' ? 'No' : 'Nein')) : null,
            'Selection' => $this->resolveSelectionValue($attrValue, $lang),
            'Dictionary' => $this->resolveDictionaryValue($attrValue, $lang),
            'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink' => $this->resolveLinkDisplayValue($attrValue->value_string),
            default => $attrValue->value_string,
        };
    }

    private function resolveSelectionValue(ProductAttributeValue $attrValue, string $lang): ?string
    {
        $entry = $attrValue->valueListEntry;
        if (!$entry) {
            return null;
        }

        return $lang === 'en' && $entry->display_value_en
            ? $entry->display_value_en
            : $entry->display_value_de;
    }

    private function resolveDictionaryValue(ProductAttributeValue $attrValue, string $lang): ?string
    {
        $entry = $attrValue->dictionaryEntry;
        if (!$entry) {
            return null;
        }

        return $lang === 'en' && $entry->short_text_en
            ? $entry->short_text_en
            : $entry->short_text_de;
    }

    private function resolveLinkDisplayValue(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json;
        }

        return $data['title'] ?? $data['url'] ?? null;
    }
}
