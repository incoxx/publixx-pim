<?php

declare(strict_types=1);

namespace App\Services\Preview;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\CompositeFormatResolver;
use App\Services\Formatting\AttributeFormattingService;
use App\Services\Inheritance\HierarchyInheritanceService;
use Illuminate\Support\Collection;

class ProductPreviewService
{
    public function __construct(
        protected HierarchyInheritanceService $hierarchyService,
    ) {}

    /**
     * Build a complete, structured preview of a product.
     *
     * @return array{stammdaten: array, attribute_sections: array, relations: array, prices: array, media: array, variants: array}
     */
    /**
     * @param string      $lang       Sprache für Labels (name_de/name_en)
     * @param string|null $filterLang Wenn gesetzt, nur Attributwerte dieser Sprache anzeigen. null = alle.
     */
    public function buildPreviewData(Product $product, string $lang, ?string $filterLang = null): array
    {
        $product->load([
            'productType',
            'masterHierarchyNode',
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.dictionaryEntry',
            'attributeValues.unit',
            'prices.priceType',
            'relations.relationType',
            'relations.targetProduct',
            'media',
            'mediaAssignments.usageType',
            'variants',
            'createdBy',
            'updatedBy',
        ]);

        return [
            'stammdaten' => $this->buildStammdaten($product, $lang),
            'attribute_sections' => $this->buildAttributeSections($product, $lang, $filterLang),
            'relations' => $this->buildRelations($product, $lang),
            'prices' => $this->buildPrices($product, $lang),
            'media' => $this->buildMedia($product, $lang),
            'variants' => $this->buildVariants($product, $lang),
        ];
    }

    private function buildStammdaten(Product $product, string $lang): array
    {
        $breadcrumb = $this->buildBreadcrumb($product, $lang);

        $productTypeName = null;
        if ($product->productType) {
            $productTypeName = $lang === 'en' && $product->productType->name_en
                ? $product->productType->name_en
                : $product->productType->name_de;
        }

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'name' => $product->name,
            'status' => $product->status,
            'product_type' => $product->productType ? [
                'id' => $product->productType->id,
                'technical_name' => $product->productType->technical_name,
                'name' => $productTypeName,
            ] : null,
            'category_breadcrumb' => $breadcrumb,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'created_by' => $product->createdBy?->name ?? $product->created_by,
            'updated_by' => $product->updatedBy?->name ?? $product->updated_by,
        ];
    }

    /**
     * Build attribute sections grouped by collection_name from hierarchy inheritance.
     */
    private function buildAttributeSections(Product $product, string $lang, ?string $filterLang = null): array
    {
        $effectiveAttributes = $this->hierarchyService->getProductAttributes($product);

        // Index existing master values (output_hierarchy_id IS NULL) by attribute_id
        // Wenn filterLang gesetzt: nur sprachlose + passende Sprachwerte
        $masterValues = $product->attributeValues->whereNull('output_hierarchy_id');
        if ($filterLang !== null) {
            $masterValues = $masterValues->filter(
                fn ($v) => $v->language === null || $v->language === $filterLang
            );
        }
        $existingValues = $masterValues->groupBy('attribute_id');

        $sections = [];
        $sectionMap = [];

        foreach ($effectiveAttributes as $assignment) {
            // Skip internal attributes in preview output
            if (!empty($assignment->is_internal)) {
                continue;
            }

            $sectionName = $assignment->collection_name ?? $assignment->attribute_view_name_de ?? ($lang === 'en' ? 'General' : 'Allgemein');
            $sectionSort = $assignment->collection_sort ?? 0;

            if (!isset($sectionMap[$sectionName])) {
                $sectionMap[$sectionName] = count($sections);
                $sections[] = [
                    'section_name' => $sectionName,
                    'section_sort' => $sectionSort,
                    'attributes' => [],
                ];
            }

            $sectionIndex = $sectionMap[$sectionName];

            $label = $lang === 'en' && $assignment->attribute_name_en
                ? $assignment->attribute_name_en
                : $assignment->attribute_name_de;

            // Get attribute values for this attribute
            $attrValues = $existingValues->get($assignment->attribute_id, collect());

            if ($attrValues->isEmpty()) {
                // Attribute exists in hierarchy but has no value
                $sections[$sectionIndex]['attributes'][] = [
                    'attribute_id' => $assignment->attribute_id,
                    'technical_name' => $assignment->attribute_technical_name,
                    'label' => $label,
                    'value' => null,
                    'display_value' => null,
                    'unit' => null,
                    'data_type' => $assignment->data_type,
                    'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                    'is_multipliable' => (bool) ($assignment->is_multipliable ?? false),
                    'language' => null,
                    'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                    'composite_format' => $assignment->composite_format ?? null,
                    'composite_expression' => $assignment->composite_expression ?? null,
                ];
            } else {
                // Multipliable einfache Attribute: Werte zu einem Eintrag zusammenfassen
                $isMultipliable = (bool) ($assignment->is_multipliable ?? false);
                if ($isMultipliable && $assignment->data_type !== 'Composite' && $attrValues->count() > 1) {
                    $sorted = $attrValues->sortBy('multiplied_index');
                    $displayValues = $sorted
                        ->map(fn ($av) => $this->resolveDisplayValue($av, $lang))
                        ->filter(fn ($v) => $v !== null && $v !== '')
                        ->values()
                        ->all();

                    $unit = $sorted->first()->unit?->abbreviation;

                    $sections[$sectionIndex]['attributes'][] = [
                        'attribute_id' => $assignment->attribute_id,
                        'technical_name' => $assignment->attribute_technical_name,
                        'label' => $label,
                        'value' => !empty($displayValues) ? implode(', ', $displayValues) : null,
                        'display_value' => !empty($displayValues) ? implode(', ', $displayValues) : null,
                        'display_values' => $displayValues,
                        'unit' => $unit,
                        'data_type' => $assignment->data_type,
                        'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                        'is_multipliable' => true,
                        'language' => $sorted->first()->language,
                        'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                        'composite_format' => $assignment->composite_format ?? null,
                        'composite_expression' => $assignment->composite_expression ?? null,
                    ];
                    continue;
                }

                foreach ($attrValues as $attrValue) {
                    $displayValue = $this->resolveDisplayValue($attrValue, $lang);
                    $unit = $attrValue->unit?->abbreviation;

                    // For link types: build link_data and ensure display_value fallback
                    $isLinkType = in_array($assignment->data_type, ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink']);
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

                    $attrEntry = [
                        'attribute_id' => $assignment->attribute_id,
                        'technical_name' => $assignment->attribute_technical_name,
                        'label' => $label,
                        'value' => $this->resolveRawValue($attrValue),
                        'display_value' => $displayValue,
                        'unit' => $unit,
                        'data_type' => $assignment->data_type,
                        'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                        'is_multipliable' => $isMultipliable,
                        'language' => $attrValue->language,
                        'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                        'composite_format' => $assignment->composite_format ?? null,
                        'composite_expression' => $assignment->composite_expression ?? null,
                    ];

                    if ($linkData) {
                        $attrEntry['link_data'] = $linkData;
                    }

                    $sections[$sectionIndex]['attributes'][] = $attrEntry;
                }
            }
        }

        // ── Composite-Kinder injizieren ──────────────────────────────────
        // Kind-Attribute von Composites sind nicht separat der Hierarchie zugewiesen,
        // müssen aber für die Vorschau-Auflösung vorhanden sein.
        foreach ($sections as &$section) {
            $compositeParentIds = [];
            foreach ($section['attributes'] as $attr) {
                if ($attr['data_type'] === 'Composite') {
                    $compositeParentIds[] = $attr['attribute_id'];
                }
            }

            if (empty($compositeParentIds)) {
                continue;
            }

            // Kind-Attribute laden (inkl. Sub-Composites)
            $children = Attribute::whereIn('parent_attribute_id', $compositeParentIds)
                ->orderBy('position')
                ->get();

            // Auch Enkel laden (für Sub-Composites)
            $subCompositeIds = $children->where('data_type', 'Composite')->pluck('id')->all();
            $grandchildren = collect();
            if (!empty($subCompositeIds)) {
                $grandchildren = Attribute::whereIn('parent_attribute_id', $subCompositeIds)
                    ->orderBy('position')
                    ->get();
            }

            $allChildren = $children->merge($grandchildren);

            foreach ($allChildren as $child) {
                $childLabel = $lang === 'en' && $child->name_en ? $child->name_en : $child->name_de;
                $childValues = $existingValues->get($child->id, collect());

                if ($childValues->isEmpty()) {
                    $section['attributes'][] = [
                        'attribute_id' => $child->id,
                        'technical_name' => $child->technical_name,
                        'label' => $childLabel,
                        'value' => null,
                        'display_value' => null,
                        'unit' => null,
                        'data_type' => $child->data_type,
                        'is_mandatory' => false,
                        'language' => null,
                        'parent_attribute_id' => $child->parent_attribute_id,
                        'composite_format' => $child->composite_format,
                        'composite_expression' => $child->composite_expression,
                    ];
                } else {
                    foreach ($childValues as $childValue) {
                        $section['attributes'][] = [
                            'attribute_id' => $child->id,
                            'technical_name' => $child->technical_name,
                            'label' => $childLabel,
                            'value' => $this->resolveRawValue($childValue),
                            'display_value' => $this->resolveDisplayValue($childValue, $lang),
                            'unit' => $childValue->unit?->abbreviation,
                            'data_type' => $child->data_type,
                            'is_mandatory' => false,
                            'language' => $childValue->language,
                            'parent_attribute_id' => $child->parent_attribute_id,
                            'composite_format' => $child->composite_format,
                            'composite_expression' => $child->composite_expression,
                        ];
                    }
                }
            }
        }
        unset($section);

        // ── Composite-Vorschauwerte berechnen ────────────────────────────
        foreach ($sections as &$section) {
            foreach ($section['attributes'] as &$attr) {
                if ($attr['data_type'] !== 'Composite' || !empty($attr['parent_attribute_id'])) {
                    continue;
                }

                $children = array_values(array_filter(
                    $section['attributes'],
                    fn ($a) => ($a['parent_attribute_id'] ?? null) === $attr['attribute_id']
                ));

                if (empty($children)) {
                    continue;
                }

                $isMultipliable = !empty($attr['is_multipliable']);

                if ($isMultipliable) {
                    // Vermehrbares Composite: multiplied_instances aufbauen
                    $childAttrs = Attribute::where('parent_attribute_id', $attr['attribute_id'])
                        ->orderBy('position')->get();
                    $childIds = $childAttrs->pluck('id')->all();

                    // Alle multiplied_index Werte sammeln
                    $allChildValues = $existingValues->filter(fn ($vals, $key) => in_array($key, $childIds));
                    $indices = $allChildValues->flatten()->pluck('multiplied_index')->unique()->sort()->values();

                    $instances = [];
                    foreach ($indices as $idx) {
                        $instanceChildren = [];
                        $childValues = [];
                        foreach ($childAttrs as $childAttr) {
                            $childLabel = $lang === 'en' && $childAttr->name_en ? $childAttr->name_en : $childAttr->name_de;
                            $av = $existingValues->get($childAttr->id, collect())
                                ->first(fn ($v) => $v->multiplied_index === $idx);
                            $dv = $av ? $this->resolveDisplayValue($av, $lang) : null;
                            $childValues[] = $dv ?? '';
                            $instanceChildren[] = [
                                'attribute_id' => $childAttr->id,
                                'label' => $childLabel,
                                'display_value' => $dv,
                                'unit' => $av?->unit?->abbreviation,
                            ];
                        }

                        $formatted = null;
                        if ($attr['composite_format']) {
                            $formatted = CompositeFormatResolver::resolve(
                                $attr['composite_format'],
                                $childAttrs->all(),
                                $childValues
                            );
                        }

                        $instances[] = [
                            '_formatted' => $formatted ?: null,
                            '_index' => $idx,
                            'children' => $instanceChildren,
                        ];
                    }

                    $attr['multiplied_instances'] = $instances;
                } else {
                    // Einfaches Composite: display_value direkt berechnen
                    $childAttrs = Attribute::where('parent_attribute_id', $attr['attribute_id'])
                        ->orderBy('position')->get();
                    $childValues = [];
                    foreach ($childAttrs as $childAttr) {
                        $av = $existingValues->get($childAttr->id, collect())->first();
                        $childValues[] = $av ? ($this->resolveDisplayValue($av, $lang) ?? '') : '';
                    }

                    if ($attr['composite_format']) {
                        $attr['display_value'] = CompositeFormatResolver::resolve(
                            $attr['composite_format'],
                            $childAttrs->all(),
                            $childValues
                        ) ?: null;
                    } elseif (!empty(array_filter($childValues, fn ($v) => $v !== ''))) {
                        $attr['display_value'] = implode(' × ', array_filter($childValues, fn ($v) => $v !== ''));
                    }
                }
            }
            unset($attr);
        }
        unset($section);

        // ── Output hierarchy attributes ──────────────────────────────────
        $outputHierarchyData = $this->hierarchyService->getProductOutputHierarchyAttributes($product);
        $processedOutputAttrIds = [];

        foreach ($outputHierarchyData as $hierarchyId => $data) {
            $hierarchy = $data['hierarchy'];
            $outputAttributes = $data['attributes'];

            $hierarchyName = $lang === 'en' && $hierarchy->name_en
                ? $hierarchy->name_en
                : $hierarchy->name_de;

            // Load channel-specific values for this hierarchy
            $channelQuery = ProductAttributeValue::where('product_id', $product->id)
                ->where('output_hierarchy_id', $hierarchyId)
                ->with(['attribute', 'valueListEntry', 'dictionaryEntry', 'unit']);
            if ($filterLang !== null) {
                $channelQuery->where(fn ($q) => $q->whereNull('language')->orWhere('language', $filterLang));
            }
            $channelValues = $channelQuery->get()->groupBy('attribute_id');

            foreach ($outputAttributes as $assignment) {
                if (!empty($assignment->is_internal)) {
                    continue;
                }

                $processedOutputAttrIds[] = $assignment->attribute_id;

                // Section name: hierarchy name + optional collection name
                $collectionPart = $assignment->collection_name ?? $assignment->attribute_view_name_de ?? null;
                $sectionName = $collectionPart
                    ? "{$hierarchyName} › {$collectionPart}"
                    : $hierarchyName;
                $sectionSort = 5000 + ($assignment->collection_sort ?? 0);

                if (!isset($sectionMap[$sectionName])) {
                    $sectionMap[$sectionName] = count($sections);
                    $sections[] = [
                        'section_name' => $sectionName,
                        'section_sort' => $sectionSort,
                        'attributes' => [],
                    ];
                }

                $sectionIndex = $sectionMap[$sectionName];
                $label = $lang === 'en' && $assignment->attribute_name_en
                    ? $assignment->attribute_name_en
                    : $assignment->attribute_name_de;

                // Prefer channel-specific value, fall back to master value
                $attrValues = $channelValues->get($assignment->attribute_id, collect());
                if ($attrValues->isEmpty()) {
                    $attrValues = $existingValues->get($assignment->attribute_id, collect());
                }

                if ($attrValues->isEmpty()) {
                    $sections[$sectionIndex]['attributes'][] = [
                        'attribute_id' => $assignment->attribute_id,
                        'technical_name' => $assignment->attribute_technical_name,
                        'label' => $label,
                        'value' => null,
                        'display_value' => null,
                        'unit' => null,
                        'data_type' => $assignment->data_type,
                        'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                        'language' => null,
                        'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                        'composite_format' => $assignment->composite_format ?? null,
                        'composite_expression' => $assignment->composite_expression ?? null,
                    ];
                } else {
                    // Multipliable einfache Attribute: Werte zusammenfassen
                    $isMultipliable = (bool) ($assignment->is_multipliable ?? false);
                    if ($isMultipliable && $assignment->data_type !== 'Composite' && $attrValues->count() > 1) {
                        $sorted = $attrValues->sortBy('multiplied_index');
                        $displayValues = $sorted
                            ->map(fn ($av) => $this->resolveDisplayValue($av, $lang))
                            ->filter(fn ($v) => $v !== null && $v !== '')
                            ->values()
                            ->all();

                        $unit = $sorted->first()->unit?->abbreviation;

                        $sections[$sectionIndex]['attributes'][] = [
                            'attribute_id' => $assignment->attribute_id,
                            'technical_name' => $assignment->attribute_technical_name,
                            'label' => $label,
                            'value' => !empty($displayValues) ? implode(', ', $displayValues) : null,
                            'display_value' => !empty($displayValues) ? implode(', ', $displayValues) : null,
                            'display_values' => $displayValues,
                            'unit' => $unit,
                            'data_type' => $assignment->data_type,
                            'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                            'is_multipliable' => true,
                            'language' => $sorted->first()->language,
                            'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                            'composite_format' => $assignment->composite_format ?? null,
                            'composite_expression' => $assignment->composite_expression ?? null,
                        ];
                        continue;
                    }

                    foreach ($attrValues as $attrValue) {
                        $displayValue = $this->resolveDisplayValue($attrValue, $lang);
                        $unit = $attrValue->unit?->abbreviation;
                        $isLinkType = in_array($assignment->data_type, ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink']);
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

                        $attrEntry = [
                            'attribute_id' => $assignment->attribute_id,
                            'technical_name' => $assignment->attribute_technical_name,
                            'label' => $label,
                            'value' => $this->resolveRawValue($attrValue),
                            'display_value' => $displayValue,
                            'unit' => $unit,
                            'data_type' => $assignment->data_type,
                            'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                            'is_multipliable' => $isMultipliable,
                            'language' => $attrValue->language,
                            'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                            'composite_format' => $assignment->composite_format ?? null,
                            'composite_expression' => $assignment->composite_expression ?? null,
                        ];

                        if ($linkData) {
                            $attrEntry['link_data'] = $linkData;
                        }

                        $sections[$sectionIndex]['attributes'][] = $attrEntry;
                    }
                }
            }
        }

        // Include link-type attributes that have values but weren't in the hierarchy assignments
        $processedAttrIds = array_merge(
            collect($effectiveAttributes)->pluck('attribute_id')->toArray(),
            $processedOutputAttrIds,
        );
        $linkDataTypes = ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink'];

        foreach ($existingValues as $attrId => $attrValueGroup) {
            if (in_array($attrId, $processedAttrIds)) {
                continue;
            }

            $firstValue = $attrValueGroup->first();
            $attr = $firstValue?->attribute;
            if (!$attr || $attr->is_internal || !in_array($attr->data_type, $linkDataTypes)) {
                continue;
            }

            $sectionName = $lang === 'en' ? 'Media & Links' : 'Medien & Links';
            if (!isset($sectionMap[$sectionName])) {
                $sectionMap[$sectionName] = count($sections);
                $sections[] = [
                    'section_name' => $sectionName,
                    'section_sort' => 9999,
                    'attributes' => [],
                ];
            }
            $sectionIndex = $sectionMap[$sectionName];

            $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;

            foreach ($attrValueGroup as $attrValue) {
                $displayValue = $this->resolveDisplayValue($attrValue, $lang);
                $unit = $attrValue->unit?->abbreviation;

                $linkData = null;
                if ($attrValue->value_string) {
                    $linkData = json_decode($attrValue->value_string, true);
                    if (!is_array($linkData) || empty($linkData['url'])) {
                        $linkData = null;
                    }
                    if (($displayValue === null || $displayValue === '') && $linkData) {
                        $displayValue = $linkData['title'] ?? $linkData['url'] ?? null;
                    }
                }

                $attrEntry = [
                    'attribute_id' => $attr->id,
                    'technical_name' => $attr->technical_name,
                    'label' => $label,
                    'value' => $this->resolveRawValue($attrValue),
                    'display_value' => $displayValue,
                    'unit' => $unit,
                    'data_type' => $attr->data_type,
                    'is_mandatory' => false,
                    'language' => $attrValue->language,
                    'parent_attribute_id' => $attr->parent_attribute_id,
                    'composite_format' => null,
                    'composite_expression' => null,
                ];

                if ($linkData) {
                    $attrEntry['link_data'] = $linkData;
                }

                $sections[$sectionIndex]['attributes'][] = $attrEntry;
            }
        }

        $this->applyFormattingRules($sections);

        // Sort sections by section_sort
        usort($sections, fn ($a, $b) => $a['section_sort'] <=> $b['section_sort']);

        return $sections;
    }

    /**
     * Berechnet 'formatted_value' für Attribute mit zugeordneter Formatierungsregel
     * (Uppercase/Lowercase/Capitalize/Regex/Zahlenformat). Nur für String-/Number-/Float-Attribute relevant.
     */
    private function applyFormattingRules(array &$sections): void
    {
        $attributeIds = [];
        foreach ($sections as $section) {
            foreach ($section['attributes'] as $attr) {
                if (in_array($attr['data_type'], ['String', 'Number', 'Float'], true) && $attr['value'] !== null) {
                    $attributeIds[] = $attr['attribute_id'];
                }
            }
        }

        if (empty($attributeIds)) {
            return;
        }

        $attributesById = Attribute::whereIn('id', array_unique($attributeIds))
            ->whereNotNull('formatting_rule_id')
            ->with('formattingRule')
            ->get()
            ->keyBy('id');

        if ($attributesById->isEmpty()) {
            return;
        }

        foreach ($sections as &$section) {
            foreach ($section['attributes'] as &$attr) {
                $attribute = $attributesById->get($attr['attribute_id']);
                if ($attribute?->formattingRule && $attr['value'] !== null) {
                    $attr['formatted_value'] = AttributeFormattingService::apply($attr['value'], $attribute->formattingRule);
                }
            }
            unset($attr);
        }
        unset($section);
    }

    private function buildRelations(Product $product, string $lang): array
    {
        return $product->relations->map(function ($relation) use ($lang) {
            $typeName = null;
            if ($relation->relationType) {
                $typeName = $lang === 'en' && $relation->relationType->name_en
                    ? $relation->relationType->name_en
                    : $relation->relationType->name_de;
            }

            return [
                'id' => $relation->id,
                'relation_type' => $typeName,
                'relation_type_technical_name' => $relation->relationType?->technical_name,
                'target_product' => $relation->targetProduct ? [
                    'id' => $relation->targetProduct->id,
                    'sku' => $relation->targetProduct->sku,
                    'name' => $relation->targetProduct->name,
                ] : null,
                'sort_order' => $relation->sort_order,
            ];
        })->values()->toArray();
    }

    private function buildPrices(Product $product, string $lang): array
    {
        return $product->prices->map(function ($price) use ($lang) {
            $typeName = null;
            if ($price->priceType) {
                $typeName = $lang === 'en' && $price->priceType->name_en
                    ? $price->priceType->name_en
                    : $price->priceType->name_de;
            }

            return [
                'id' => $price->id,
                'price_type' => $typeName,
                'price_type_technical_name' => $price->priceType?->technical_name,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'valid_from' => $price->valid_from?->format('Y-m-d'),
                'valid_to' => $price->valid_to?->format('Y-m-d'),
                'price_region' => $price->priceRegion?->code,
                'price_region_name' => $price->priceRegion?->name,
                'scale_from' => $price->scale_from,
                'scale_to' => $price->scale_to,
            ];
        })->values()->toArray();
    }

    private function buildMedia(Product $product, string $lang): array
    {
        return $product->mediaAssignments->map(function ($assignment) use ($lang) {
            $media = $assignment->media;
            if (!$media) {
                return null;
            }

            return [
                'id' => $media->id,
                'url' => '/api/v1/media/file/' . $media->file_name,
                'file_name' => $media->file_name,
                'alt' => $lang === 'en' && $media->alt_text_en ? $media->alt_text_en : ($media->alt_text_de ?? null),
                'is_primary' => (bool) $assignment->is_primary,
                'usage_type' => $assignment->usageType ? [
                    'id' => $assignment->usageType->id,
                    'technical_name' => $assignment->usageType->technical_name,
                    'name_de' => $assignment->usageType->name_de,
                    'name_en' => $assignment->usageType->name_en,
                ] : null,
                'media_type' => $media->media_type,
                'sort_order' => $assignment->sort_order ?? 0,
            ];
        })->filter()->values()->toArray();
    }

    private function buildVariants(Product $product, string $lang): array
    {
        if ($product->variants->isEmpty()) {
            return [];
        }

        // Get variant attributes (is_variant_attribute = true, not internal)
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
                    'attribute_id' => $attr->id,
                    'technical_name' => $attr->technical_name,
                    'label' => $label,
                    'value' => $attrValue ? $this->resolveDisplayValue($attrValue, $lang) : null,
                    'unit' => $attrValue?->unit?->abbreviation,
                    'data_type' => $attr->data_type,
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

    /**
     * Build breadcrumb from materialized path (pattern from CatalogController).
     */
    private function buildBreadcrumb(Product $product, string $lang): array
    {
        $breadcrumb = [];

        if (!$product->masterHierarchyNode) {
            return $breadcrumb;
        }

        $node = $product->masterHierarchyNode;
        $ancestors = HierarchyNode::ancestorsOf($node->path)
            ->orderBy('depth')
            ->get();

        foreach ($ancestors as $ancestor) {
            $breadcrumb[] = [
                'id' => $ancestor->id,
                'name' => $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de,
            ];
        }

        // Add the current node itself
        $breadcrumb[] = [
            'id' => $node->id,
            'name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
        ];

        return $breadcrumb;
    }

    /**
     * Resolve attribute display value (pattern from CatalogProductDetailResource).
     */
    private function resolveDisplayValue(ProductAttributeValue $attrValue, string $lang): ?string
    {
        $attr = $attrValue->attribute;
        if (!$attr) {
            return null;
        }

        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null
                ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.')
                : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null
                ? ($attrValue->value_flag ? ($lang === 'en' ? 'Yes' : 'Ja') : ($lang === 'en' ? 'No' : 'Nein'))
                : null,
            'Selection' => $this->resolveSelectionValue($attrValue, $lang),
            'MultiSelection' => $this->resolveMultiSelectionValue($attrValue, $lang),
            'Dictionary' => $this->resolveDictionaryValue($attrValue, $lang),
            'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink' => $this->resolveLinkDisplayValue($attrValue->value_string),
            'DelimitedValue' => $attrValue->value_string
                ? implode(', ', array_map('trim', explode($attr->delimiter ?? '|', $attrValue->value_string)))
                : null,
            default => $attrValue->value_string,
        };
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

    private function resolveMultiSelectionValue(ProductAttributeValue $attrValue, string $lang): ?string
    {
        $ids = json_decode($attrValue->value_string ?? '', true);
        if (!is_array($ids) || empty($ids)) {
            return null;
        }

        $entries = \App\Models\ValueListEntry::whereIn('id', $ids)->get();
        $labels = $entries->map(fn ($e) => $lang === 'en' && $e->display_value_en
            ? $e->display_value_en
            : $e->display_value_de
        )->filter()->values();

        return $labels->isNotEmpty() ? $labels->implode(', ') : null;
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

    /**
     * Get the raw value from an attribute value record.
     */
    private function resolveRawValue(ProductAttributeValue $attrValue): mixed
    {
        return $attrValue->value_string
            ?? $attrValue->value_number
            ?? $attrValue->value_date
            ?? $attrValue->value_flag
            ?? $attrValue->value_selection_id;
    }
}
