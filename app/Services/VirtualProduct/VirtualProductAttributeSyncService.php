<?php

declare(strict_types=1);

namespace App\Services\VirtualProduct;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\VirtualProductInheritanceRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vererbt Attributwerte eines virtuellen Produkts ("Klammer") an dessen
 * aktuelle Mitglieder (Phase 1: nur Attribute).
 *
 * Ein Mitglied gehört zu höchstens einem virtuellen Produkt gleichzeitig.
 * Gehört ein Kandidat bereits zu einem ANDEREN virtuellen Produkt (erkennbar
 * an product_attribute_values.inherited_from_virtual_product_id), wird er
 * komplett übersprungen — nichts wird angetastet, der Konflikt wird nur
 * im Report gemeldet.
 *
 * Sync ist deklarativ ("Terraform-artig"): bei jedem Lauf wird der
 * Ziel-Zustand komplett aus den aktuellen Regeln + Werten + Mitgliedern neu
 * berechnet. Das hält das System nachvollziehbar (kein "Wildwuchs" durch
 * gelöschte Regeln oder ausgeschiedene Mitglieder):
 *   - Mitglied nicht mehr Teil des Clusters  → all seine von diesem
 *     virtuellen Produkt vererbten Werte werden entfernt
 *   - Regel wurde entfernt                   → zuvor vererbter Wert für
 *     dieses Attribut wird beim Mitglied entfernt
 *   - Vererbte Zeile ohne aktuelle Quelle     → entfernt (z.B. Sprache/
 *     Multiplied-Index auf der Klammer gelöscht)
 */
class VirtualProductAttributeSyncService
{
    public function __construct(private readonly VirtualProductResolver $resolver) {}

    /**
     * @return array{
     *     member_count: int,
     *     attribute_count: int,
     *     values_created: int,
     *     values_updated: int,
     *     values_kept_local: int,
     *     values_overridden: int,
     *     values_removed: int,
     *     skipped_members: array<int, array{id: string, sku: string, reason: string}>,
     *     released_members: array<string, string>,
     * }
     */
    /**
     * @param  array<int, string>|null  $memberIds  Bereits aufgelöste Cluster-Mitglieder
     *   (z.B. vom Aufrufer einmalig für Attribut- und Medien-Sync gemeinsam ermittelt,
     *   um die Live-Auflösung — Suchprofil/PQL-Ausführung — nicht doppelt auszuführen).
     *   Wird nichts übergeben, löst der Service die Mitglieder selbst auf.
     */
    public function sync(Product $virtualProduct, ?array $memberIds = null): array
    {
        $report = [
            'member_count' => 0,
            'attribute_count' => 0,
            'values_created' => 0,
            'values_updated' => 0,
            'values_kept_local' => 0,
            'values_overridden' => 0,
            'values_removed' => 0,
            'skipped_members' => [],
            'released_members' => [],
        ];

        $definition = $virtualProduct->virtualDefinition;
        if ($definition === null) {
            return $report;
        }

        $rules = $virtualProduct->inheritanceRules()->get()->keyBy('attribute_id');
        $report['attribute_count'] = $rules->count();

        DB::transaction(function () use ($virtualProduct, $definition, $rules, $memberIds, &$report) {
            $memberIds = collect($memberIds ?? $this->resolver->memberIds($definition));

            $this->releaseFormerMembers($virtualProduct, $memberIds, $report);

            foreach ($memberIds as $memberId) {
                $conflictOwnerId = ProductAttributeValue::where('product_id', $memberId)
                    ->whereNotNull('inherited_from_virtual_product_id')
                    ->where('inherited_from_virtual_product_id', '!=', $virtualProduct->id)
                    ->value('inherited_from_virtual_product_id');

                if ($conflictOwnerId) {
                    $owner = Product::find($conflictOwnerId);
                    $member = Product::find($memberId);
                    $report['skipped_members'][] = [
                        'id' => $memberId,
                        'sku' => $member->sku ?? $memberId,
                        'reason' => 'Bereits Mitglied von Cluster "' . ($owner->sku ?? $conflictOwnerId) . '"',
                    ];
                    continue;
                }

                $report['member_count']++;
                $this->reconcileMember($virtualProduct, $memberId, $rules, $report);
            }
        });

        return $report;
    }

    /**
     * Entfernt vererbte Werte bei Produkten, die früher Mitglied waren,
     * es aber jetzt (laut aktueller Live-Auflösung) nicht mehr sind.
     */
    private function releaseFormerMembers(Product $virtualProduct, Collection $currentMemberIds, array &$report): void
    {
        $previouslySyncedMemberIds = ProductAttributeValue::where('inherited_from_virtual_product_id', $virtualProduct->id)
            ->distinct()
            ->pluck('product_id');

        $releasedIds = $previouslySyncedMemberIds->diff($currentMemberIds);
        if ($releasedIds->isEmpty()) {
            return;
        }

        $removed = ProductAttributeValue::where('inherited_from_virtual_product_id', $virtualProduct->id)
            ->whereIn('product_id', $releasedIds)
            ->delete();

        $report['values_removed'] += $removed;
        $report['released_members'] = Product::whereIn('id', $releasedIds)->pluck('sku', 'id')->all();
    }

    /**
     * @param  Collection<string, VirtualProductInheritanceRule>  $rules
     */
    private function reconcileMember(Product $virtualProduct, string $memberId, Collection $rules, array &$report): void
    {
        // Regeln, die inzwischen entfernt wurden: zuvor vererbte Werte für
        // diese Attribute beim Mitglied ebenfalls entfernen.
        $previouslySyncedAttributeIds = ProductAttributeValue::where('product_id', $memberId)
            ->where('inherited_from_virtual_product_id', $virtualProduct->id)
            ->pluck('attribute_id')
            ->unique();

        $staleAttributeIds = $previouslySyncedAttributeIds->diff($rules->keys());
        if ($staleAttributeIds->isNotEmpty()) {
            $removed = ProductAttributeValue::where('product_id', $memberId)
                ->where('inherited_from_virtual_product_id', $virtualProduct->id)
                ->whereIn('attribute_id', $staleAttributeIds)
                ->delete();
            $report['values_removed'] += $removed;
        }

        foreach ($rules as $rule) {
            $this->reconcileAttribute($virtualProduct, $memberId, $rule, $report);
        }
    }

    private function reconcileAttribute(Product $virtualProduct, string $memberId, VirtualProductInheritanceRule $rule, array &$report): void
    {
        $sourceRows = ProductAttributeValue::where('product_id', $virtualProduct->id)
            ->where('attribute_id', $rule->attribute_id)
            ->get();

        $existingRows = ProductAttributeValue::where('product_id', $memberId)
            ->where('attribute_id', $rule->attribute_id)
            ->get()
            ->keyBy(fn (ProductAttributeValue $v) => $this->rowKey($v->language, $v->multiplied_index));

        $sourceKeys = [];

        foreach ($sourceRows as $source) {
            $key = $this->rowKey($source->language, $source->multiplied_index);
            $sourceKeys[] = $key;
            $existing = $existingRows->get($key);

            $fields = [
                'value_string' => $source->value_string,
                'value_number' => $source->value_number,
                'value_date' => $source->value_date,
                'value_flag' => $source->value_flag,
                'value_selection_id' => $source->value_selection_id,
                'unit_id' => $source->unit_id,
                'comparison_operator_id' => $source->comparison_operator_id,
                'is_inherited' => true,
                'inherited_from_virtual_product_id' => $virtualProduct->id,
            ];

            if ($existing === null) {
                ProductAttributeValue::create(array_merge($fields, [
                    'product_id' => $memberId,
                    'attribute_id' => $rule->attribute_id,
                    'language' => $source->language,
                    'multiplied_index' => $source->multiplied_index,
                ]));
                $report['values_created']++;
                continue;
            }

            // Zeile stammt bereits aus diesem virtuellen Produkt → kein
            // Konflikt, sondern nur eine Aktualisierung des vererbten Werts.
            if ($existing->inherited_from_virtual_product_id === $virtualProduct->id) {
                $existing->update($fields);
                $report['values_updated']++;
                continue;
            }

            // Lokaler (oder anderweitig ererbter) Wert vorhanden → Konfliktregel.
            if ($rule->conflict_mode === VirtualProductInheritanceRule::CONFLICT_KEEP_LOCAL) {
                $report['values_kept_local']++;
                continue;
            }

            $existing->update($fields);
            $report['values_overridden']++;
        }

        // Zuvor von uns vererbte Zeilen, deren Quelle nicht mehr existiert
        // (z.B. Sprache/Multiplied-Index auf der Klammer entfernt).
        foreach ($existingRows->keys()->diff($sourceKeys) as $staleKey) {
            $existing = $existingRows->get($staleKey);
            if ($existing->inherited_from_virtual_product_id === $virtualProduct->id) {
                $existing->delete();
                $report['values_removed']++;
            }
        }
    }

    private function rowKey(?string $language, int $multipliedIndex): string
    {
        return ($language ?? '_') . ':' . $multipliedIndex;
    }
}
