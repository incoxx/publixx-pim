<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\AttributeMapping;
use App\Models\AttributeMappingRule;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Synchronisiert gemappte Attributwerte in die Datenbank.
 *
 * Wird aufgerufen bei:
 * - Auto-Sync: Observer erkennt Quell-Wert-Änderung → syncProduct()
 * - Manuell: User klickt "Sync" → syncProduct() oder syncAll()
 */
class AttributeMappingSyncService
{
    private AttributeMappingService $mappingService;

    public function __construct(AttributeMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * Alle Mappings für ein einzelnes Produkt synchronisieren.
     *
     * Ermittelt alle Hierarchie-Paare mit Mappings und schreibt
     * die berechneten Werte als ProductAttributeValue (output_hierarchy_id-scoped).
     *
     * @return array Statistik: ['created' => int, 'updated' => int, 'skipped' => int]
     */
    public function syncProduct(Product $product, ?string $sourceHierarchyId = null, ?string $targetHierarchyId = null): array
    {
        $product->load(['attributeValues.attribute', 'attributeValues.valueListEntry', 'attributeValues.unit']);

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        // Alle relevanten Mapping-Paare ermitteln
        $query = AttributeMapping::select('source_hierarchy_id', 'target_hierarchy_id')
            ->distinct();

        if ($sourceHierarchyId) {
            $query->where('source_hierarchy_id', $sourceHierarchyId);
        }
        if ($targetHierarchyId) {
            $query->where('target_hierarchy_id', $targetHierarchyId);
        }

        $pairs = $query->get();

        foreach ($pairs as $pair) {
            $resolved = $this->mappingService->resolveForProduct(
                $product,
                $pair->source_hierarchy_id,
                $pair->target_hierarchy_id
            );

            // Mappings für dieses Paar laden (für Ziel-Attribut-IDs)
            $mappings = AttributeMapping::with('targetAttribute')
                ->where('source_hierarchy_id', $pair->source_hierarchy_id)
                ->where('target_hierarchy_id', $pair->target_hierarchy_id)
                ->get();

            foreach ($mappings as $mapping) {
                $targetTechName = $mapping->targetAttribute->technical_name;
                $value = $resolved[$targetTechName] ?? null;

                if ($value === null) {
                    $stats['skipped']++;
                    continue;
                }

                $result = $this->writeTargetValue(
                    $product,
                    $mapping->target_attribute_id,
                    $pair->target_hierarchy_id,
                    $value,
                    $mapping->targetAttribute
                );

                $stats[$result]++;
            }
        }

        return $stats;
    }

    /**
     * Auto-Sync: Prüft ob ein geänderter Attributwert Mapping-Regeln auslöst.
     *
     * Wird vom AttributeValueObserver aufgerufen. Nur Quell-Attribute
     * ohne output_hierarchy_id (= Master-Werte) lösen Sync aus.
     */
    public function syncForAttributeChange(string $productId, string $attributeId): void
    {
        // Nur Master-Werte (ohne output_hierarchy_id) triggern Sync
        $mappings = AttributeMapping::where('source_attribute_id', $attributeId)->get();

        if ($mappings->isEmpty()) {
            return; // Dieses Attribut hat keine Mapping-Regeln
        }

        $product = Product::with([
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.unit',
        ])->find($productId);

        if (!$product) {
            return;
        }

        foreach ($mappings as $mapping) {
            $resolved = $this->mappingService->resolveMapping(
                $product,
                $mapping,
                $mapping->target_hierarchy_id
            );

            if ($resolved !== null) {
                $mapping->load('targetAttribute');
                $this->writeTargetValue(
                    $product,
                    $mapping->target_attribute_id,
                    $mapping->target_hierarchy_id,
                    $resolved,
                    $mapping->targetAttribute
                );
            }
        }

        Log::debug('AttributeMappingSyncService: Auto-sync für Attribut-Änderung', [
            'product_id' => $productId,
            'attribute_id' => $attributeId,
            'mappings_checked' => $mappings->count(),
        ]);
    }

    /**
     * Batch-Sync: Alle Produkte für ein Hierarchie-Paar synchronisieren.
     *
     * @return array Statistik: ['products' => int, 'created' => int, 'updated' => int, 'skipped' => int]
     */
    public function syncAll(string $sourceHierarchyId, string $targetHierarchyId, ?array $productIds = null): array
    {
        $stats = ['products' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

        $query = Product::with([
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.unit',
        ])->where('status', 'active');

        if ($productIds) {
            $query->whereIn('id', $productIds);
        }

        $query->chunk(100, function ($products) use ($sourceHierarchyId, $targetHierarchyId, &$stats) {
            foreach ($products as $product) {
                $result = $this->syncProduct($product, $sourceHierarchyId, $targetHierarchyId);
                $stats['products']++;
                $stats['created'] += $result['created'];
                $stats['updated'] += $result['updated'];
                $stats['skipped'] += $result['skipped'];
            }
        });

        Log::info('AttributeMappingSyncService: Batch-Sync abgeschlossen', $stats);

        return $stats;
    }

    /**
     * Ziel-Wert in die Datenbank schreiben.
     *
     * Respektiert manuelle Overrides: Wenn ein Wert mit output_hierarchy_id
     * bereits existiert und NICHT von einem Mapping stammt (is_inherited=false),
     * wird er NICHT überschrieben.
     *
     * @return string 'created', 'updated', oder 'skipped'
     */
    private function writeTargetValue(
        Product $product,
        string $targetAttributeId,
        string $targetHierarchyId,
        mixed $value,
        $targetAttribute
    ): string {
        $existing = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $targetAttributeId)
            ->where('output_hierarchy_id', $targetHierarchyId)
            ->whereNull('language')
            ->where('multiplied_index', 0)
            ->first();

        // Manueller Override? Nicht überschreiben.
        if ($existing && !$existing->is_inherited) {
            return 'skipped';
        }

        // Wert-Felder bestimmen basierend auf Datentyp
        $fields = $this->buildValueFields($value, $targetAttribute);
        $fields['is_inherited'] = true; // Markiert als "vom Mapping erzeugt"
        $fields['output_hierarchy_id'] = $targetHierarchyId;

        if ($existing) {
            $existing->update($fields);
            return 'updated';
        }

        ProductAttributeValue::create(array_merge([
            'product_id' => $product->id,
            'attribute_id' => $targetAttributeId,
            'language' => null,
            'multiplied_index' => 0,
        ], $fields));

        return 'created';
    }

    /**
     * Wert-Felder für ProductAttributeValue basierend auf Datentyp aufbauen.
     */
    private function buildValueFields(mixed $value, $targetAttribute): array
    {
        $dataType = $targetAttribute->data_type ?? 'String';

        return match ($dataType) {
            'Number', 'Float' => [
                'value_number' => is_numeric($value) ? (float) $value : null,
                'value_string' => null,
                'value_flag' => null,
                'unit_id' => $targetAttribute->default_unit_id,
            ],
            'Flag' => [
                'value_flag' => is_bool($value) ? $value : ($value === 'true' || $value === '1'),
                'value_string' => null,
                'value_number' => null,
            ],
            'Selection' => [
                'value_string' => is_string($value) ? $value : (string) $value,
                'value_number' => null,
                'value_flag' => null,
            ],
            default => [
                'value_string' => is_string($value) ? $value : (string) $value,
                'value_number' => null,
                'value_flag' => null,
            ],
        };
    }
}
