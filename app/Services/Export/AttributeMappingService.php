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
     * Gecachete Mappings pro Hierarchie-Paar (innerhalb eines Request-Lifecycle).
     */
    private array $mappingCache = [];
    private array $ruleCache = [];
    private array $attributeCache = [];

    /**
     * Alle Ziel-Attributwerte für ein Produkt ermitteln.
     *
     * @param  string $sourceHierarchyId  Quell-Hierarchie (z.B. Master)
     * @param  string $targetHierarchyId  Ziel-Hierarchie (z.B. ETIM)
     * @return array<string, mixed> Assoziatives Array: target_attribute_technical_name => resolved_value
     */
    public function resolveForProduct(
        Product $product,
        string $sourceHierarchyId,
        string $targetHierarchyId,
        ?string $language = null
    ): array {
        $cacheKey = "{$sourceHierarchyId}:{$targetHierarchyId}";
        $mappings = $this->getMappings($sourceHierarchyId, $targetHierarchyId);
        $rules = $this->getRules($sourceHierarchyId, $targetHierarchyId);
        $result = [];

        // 1. Einfache Mappings (source → target)
        foreach ($mappings as $mapping) {
            $targetTechName = $mapping->targetAttribute->technical_name;
            $value = $this->resolveMapping($product, $mapping, $targetHierarchyId, $language);

            if ($value !== null) {
                $result[$targetTechName] = $value;
            }
        }

        // 2. Bedingte Regeln auswerten
        foreach ($rules as $rule) {
            $conditionValue = $this->getAttributeRawValue($product, $rule->condition_attribute_id, $language);

            if ($rule->evaluateCondition($conditionValue)) {
                foreach ($rule->actions as $action) {
                    $targetAttr = $this->findAttribute($action['target_attribute_id']);
                    if (!$targetAttr) {
                        continue;
                    }

                    $actionValue = $this->resolveActionValue($action, $product, $language);
                    if ($actionValue !== null) {
                        // Bedingte Regel überschreibt NICHT einen direkten Override
                        $directValue = $this->getDirectValue($product, $targetAttr->id, $targetHierarchyId, $language);
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
        string $targetHierarchyId,
        ?string $language = null
    ): mixed {
        // 1. Direkter Wert? (manuell gepflegt, output_hierarchy_id-scoped)
        $directValue = $this->getDirectValue(
            $product,
            $mapping->target_attribute_id,
            $targetHierarchyId,
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
     * Direkten Wert für ein Ziel-Attribut in einer Ziel-Hierarchie holen.
     */
    protected function getDirectValue(
        Product $product,
        string $attributeId,
        string $targetHierarchyId,
        ?string $language
    ): mixed {
        $values = $product->attributeValues ?? collect();

        $av = $values->first(function (ProductAttributeValue $val) use ($attributeId, $targetHierarchyId, $language) {
            return $val->attribute_id === $attributeId
                && $val->output_hierarchy_id === $targetHierarchyId
                && ($val->language === $language || $val->language === null);
        });

        if ($av === null) {
            return null;
        }

        return $this->extractValue($av, $language);
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

        return $this->extractValue($av, $language);
    }

    /**
     * Wert aus einem ProductAttributeValue extrahieren.
     */
    protected function extractValue(ProductAttributeValue $av, ?string $language = null): mixed
    {
        if ($av->value_selection_id !== null) {
            $entry = $av->valueListEntry;
            if ($entry) {
                $langField = 'display_value_' . ($language ?? 'de');
                return $entry->{$langField} ?? $entry->display_value_de ?? $entry->technical_name;
            }
            return $av->value_selection_id;
        }

        return $av->value_string
            ?? $av->value_number
            ?? $av->value_flag
            ?? $av->value_date?->format('Y-m-d');
    }

    /**
     * Attribut per ID finden (gecacht, vermeidet N+1 Queries).
     */
    protected function findAttribute(string $attributeId): ?Attribute
    {
        if (!isset($this->attributeCache[$attributeId])) {
            $this->attributeCache[$attributeId] = Attribute::find($attributeId);
        }

        return $this->attributeCache[$attributeId];
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
     * Mappings für ein Hierarchie-Paar laden (gecacht).
     */
    protected function getMappings(string $sourceHierarchyId, string $targetHierarchyId): Collection
    {
        $key = "{$sourceHierarchyId}:{$targetHierarchyId}";
        if (!isset($this->mappingCache[$key])) {
            $this->mappingCache[$key] = AttributeMapping::with(['sourceAttribute', 'targetAttribute'])
                ->where('source_hierarchy_id', $sourceHierarchyId)
                ->where('target_hierarchy_id', $targetHierarchyId)
                ->get();
        }

        return $this->mappingCache[$key];
    }

    /**
     * Bedingte Regeln für ein Hierarchie-Paar laden (gecacht).
     */
    protected function getRules(string $sourceHierarchyId, string $targetHierarchyId): Collection
    {
        $key = "{$sourceHierarchyId}:{$targetHierarchyId}";
        if (!isset($this->ruleCache[$key])) {
            $this->ruleCache[$key] = AttributeMappingRule::with('conditionAttribute')
                ->where('source_hierarchy_id', $sourceHierarchyId)
                ->where('target_hierarchy_id', $targetHierarchyId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        return $this->ruleCache[$key];
    }

    /**
     * Cache leeren (z.B. nach Mapping-Änderung).
     */
    public function clearCache(): void
    {
        $this->mappingCache = [];
        $this->ruleCache = [];
        $this->attributeCache = [];
    }
}
