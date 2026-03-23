<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\AttributeMapping;
use App\Models\AttributeMappingRule;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Collection;

/**
 * Zentrale Wert-Sync-Logik für Attribut-Mappings.
 *
 * Ermittelt Ziel-Attributwerte für einen Export durch:
 * 1. Direkter Wert (manuell gepflegt, output_hierarchy_id-scoped) → Override gewinnt
 * 2. Mapping-Regel → Wert vom Quell-Attribut holen + Transform anwenden
 * 3. Bedingte Regeln → WENN Bedingung erfüllt DANN Zielwerte setzen
 */
class AttributeMappingService
{
    /**
     * Gecachete Mappings pro Hierarchie (innerhalb eines Request-Lifecycle).
     */
    private array $mappingCache = [];
    private array $ruleCache = [];

    /**
     * Alle Ziel-Attributwerte für ein Produkt in einer Klassifikation ermitteln.
     *
     * @return array<string, mixed> Assoziatives Array: target_attribute_technical_name => resolved_value
     */
    public function resolveForProduct(Product $product, string $outputHierarchyId, ?string $language = null): array
    {
        $mappings = $this->getMappingsForHierarchy($outputHierarchyId);
        $rules = $this->getRulesForHierarchy($outputHierarchyId);
        $result = [];

        // 1. Einfache Mappings (source → target)
        foreach ($mappings as $mapping) {
            $targetTechName = $mapping->targetAttribute->technical_name;
            $value = $this->resolveMapping($product, $mapping, $outputHierarchyId, $language);

            if ($value !== null) {
                $result[$targetTechName] = $value;
            }
        }

        // 2. Bedingte Regeln auswerten
        foreach ($rules as $rule) {
            $conditionValue = $this->getAttributeRawValue($product, $rule->condition_attribute_id, $language);

            if ($rule->evaluateCondition($conditionValue)) {
                foreach ($rule->actions as $action) {
                    $targetAttr = Attribute::find($action['target_attribute_id']);
                    if (!$targetAttr) {
                        continue;
                    }

                    $actionValue = $this->resolveActionValue($action, $product, $language);
                    if ($actionValue !== null) {
                        // Bedingte Regel überschreibt NICHT einen direkten Override
                        $directValue = $this->getDirectValue($product, $targetAttr->id, $outputHierarchyId, $language);
                        if ($directValue === null) {
                            $result[$targetAttr->technical_name] = $actionValue;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Einzelnes Mapping auflösen.
     *
     * Priorität: Direkt-Wert > Mapping-Regel
     */
    public function resolveMapping(
        Product $product,
        AttributeMapping $mapping,
        string $outputHierarchyId,
        ?string $language = null
    ): mixed {
        // 1. Direkter Wert? (manuell gepflegt, output_hierarchy_id-scoped)
        $directValue = $this->getDirectValue(
            $product,
            $mapping->target_attribute_id,
            $outputHierarchyId,
            $language
        );

        if ($directValue !== null) {
            return $directValue;
        }

        // 2. Wert vom Quell-Attribut holen
        $sourceValue = $this->getAttributeRawValue(
            $product,
            $mapping->source_attribute_id,
            $language
        );

        if ($sourceValue === null) {
            return null;
        }

        // 3. Transform anwenden
        return $this->applyTransform($sourceValue, $mapping);
    }

    /**
     * Direkten Wert für ein Ziel-Attribut in einer Output-Hierarchie holen.
     */
    protected function getDirectValue(
        Product $product,
        string $attributeId,
        string $outputHierarchyId,
        ?string $language
    ): mixed {
        $values = $product->attributeValues ?? collect();

        $av = $values->first(function (ProductAttributeValue $val) use ($attributeId, $outputHierarchyId, $language) {
            return $val->attribute_id === $attributeId
                && $val->output_hierarchy_id === $outputHierarchyId
                && ($val->language === $language || $val->language === null);
        });

        if ($av === null) {
            return null;
        }

        return $this->extractValue($av);
    }

    /**
     * Roh-Wert eines Attributs vom Produkt holen (ohne output_hierarchy_id Scope).
     */
    protected function getAttributeRawValue(Product $product, string $attributeId, ?string $language): mixed
    {
        $values = $product->attributeValues ?? collect();

        $av = $values->first(function (ProductAttributeValue $val) use ($attributeId, $language) {
            return $val->attribute_id === $attributeId
                && $val->output_hierarchy_id === null
                && ($val->language === $language || $val->language === null);
        });

        if ($av === null) {
            return null;
        }

        return $this->extractValue($av);
    }

    /**
     * Wert aus einem ProductAttributeValue extrahieren.
     */
    protected function extractValue(ProductAttributeValue $av): mixed
    {
        if ($av->value_selection_id !== null) {
            $entry = $av->valueListEntry;
            return $entry?->display_value_de ?? $entry?->technical_name ?? $av->value_selection_id;
        }

        return $av->value_string
            ?? $av->value_number
            ?? $av->value_flag
            ?? $av->value_date?->format('Y-m-d');
    }

    /**
     * Transform auf einen Quell-Wert anwenden.
     */
    protected function applyTransform(mixed $sourceValue, AttributeMapping $mapping): mixed
    {
        return match ($mapping->transform_type) {
            'direct' => $sourceValue,
            'unit_convert' => $this->applyUnitConvert($sourceValue, $mapping->transform_config ?? []),
            'value_map' => $this->applyValueMap($sourceValue, $mapping->transform_config ?? []),
            default => $sourceValue,
        };
    }

    /**
     * Einheiten-Umrechnung: z.B. mm → cm mit Faktor 0.1
     */
    protected function applyUnitConvert(mixed $value, array $config): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }

        $factor = $config['factor'] ?? 1.0;

        return round((float) $value * $factor, 6);
    }

    /**
     * Wert-Zuordnung: z.B. "IP55" → "EV000123"
     */
    protected function applyValueMap(mixed $value, array $config): mixed
    {
        $mapping = $config['mapping'] ?? [];

        return $mapping[(string) $value] ?? $config['default'] ?? $value;
    }

    /**
     * Wert einer bedingten Regel-Aktion ermitteln.
     */
    protected function resolveActionValue(array $action, Product $product, ?string $language): mixed
    {
        $valueType = $action['value_type'] ?? 'static';

        if ($valueType === 'source_attribute' && isset($action['source_attribute_id'])) {
            return $this->getAttributeRawValue($product, $action['source_attribute_id'], $language);
        }

        return $action['value'] ?? null;
    }

    /**
     * Mappings für eine Hierarchie laden (gecacht).
     */
    protected function getMappingsForHierarchy(string $hierarchyId): Collection
    {
        if (!isset($this->mappingCache[$hierarchyId])) {
            $this->mappingCache[$hierarchyId] = AttributeMapping::with(['sourceAttribute', 'targetAttribute'])
                ->where('output_hierarchy_id', $hierarchyId)
                ->get();
        }

        return $this->mappingCache[$hierarchyId];
    }

    /**
     * Bedingte Regeln für eine Hierarchie laden (gecacht).
     */
    protected function getRulesForHierarchy(string $hierarchyId): Collection
    {
        if (!isset($this->ruleCache[$hierarchyId])) {
            $this->ruleCache[$hierarchyId] = AttributeMappingRule::with('conditionAttribute')
                ->where('output_hierarchy_id', $hierarchyId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        return $this->ruleCache[$hierarchyId];
    }

    /**
     * Cache leeren (z.B. nach Mapping-Änderung).
     */
    public function clearCache(): void
    {
        $this->mappingCache = [];
        $this->ruleCache = [];
    }
}
