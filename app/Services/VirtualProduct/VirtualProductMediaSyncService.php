<?php

declare(strict_types=1);

namespace App\Services\VirtualProduct;

use App\Models\Product;
use App\Models\ProductMediaAssignment;
use App\Models\VirtualProductMediaInheritanceRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Vererbt Medien-Zuordnungen eines virtuellen Produkts ("Klammer") an dessen
 * aktuelle Mitglieder — Pendant zu VirtualProductAttributeSyncService, aber
 * für Medien (product_media_assignments) statt Attribute.
 *
 * Ein Mitglied gehört bezüglich Medien zu höchstens einem virtuellen Produkt
 * gleichzeitig. Gehört ein Kandidat bereits zu einem ANDEREN virtuellen
 * Produkt (erkennbar an product_media_assignments.inherited_from_virtual_product_id),
 * wird er komplett übersprungen — nichts wird angetastet, der Konflikt wird
 * nur im Report gemeldet. Diese Prüfung ist unabhängig von der
 * Attribut-Vererbung (separate Ressource, eigener Konfliktkreis).
 *
 * Da product_media_assignments je Usage-Type mehrere Zuordnungen erlaubt
 * (z.B. mehrere Galerie-Bilder), wird der Konflikt pro Usage-Type
 * entschieden, nicht pro einzelner Zuordnung:
 *   - existieren lokale (nicht von diesem virtuellen Produkt vererbte)
 *     Zuordnungen des Mitglieds für den Usage-Type, entscheidet
 *     conflict_mode der Regel:
 *       - keep_local:     Usage-Type des Mitglieds bleibt unangetastet
 *       - force_override: lokale Zuordnungen werden entfernt, die
 *                          vererbten Zuordnungen der Klammer treten an ihre Stelle
 *   - existieren keine lokalen Zuordnungen, wird deklarativ synchronisiert
 *     (unabhängig vom conflict_mode): unsere vererbten Zuordnungen werden
 *     an die aktuellen Zuordnungen der Klammer angeglichen.
 *
 * Sync ist deklarativ ("Terraform-artig"), analog zum Attribut-Sync:
 *   - Mitglied nicht mehr Teil des Clusters → all seine von diesem
 *     virtuellen Produkt vererbten Zuordnungen werden entfernt
 *   - Regel wurde entfernt                  → zuvor vererbte Zuordnungen für
 *     diesen Usage-Type werden beim Mitglied entfernt
 *   - Vererbte Zuordnung ohne aktuelle Quelle → entfernt (Medium/Motiv auf
 *     der Klammer für diesen Usage-Type entfernt)
 */
class VirtualProductMediaSyncService
{
    public function __construct(private readonly VirtualProductResolver $resolver) {}

    /**
     * @return array{
     *     member_count: int,
     *     usage_type_count: int,
     *     assignments_created: int,
     *     assignments_updated: int,
     *     assignments_kept_local: int,
     *     assignments_overridden: int,
     *     assignments_removed: int,
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
            'usage_type_count' => 0,
            'assignments_created' => 0,
            'assignments_updated' => 0,
            'assignments_kept_local' => 0,
            'assignments_overridden' => 0,
            'assignments_removed' => 0,
            'skipped_members' => [],
            'released_members' => [],
        ];

        $definition = $virtualProduct->virtualDefinition;
        if ($definition === null) {
            Log::info('VirtualProductMediaSyncService: kein Sync — virtuelles Produkt hat keine Cluster-Definition', [
                'virtual_product_id' => $virtualProduct->id,
                'sku' => $virtualProduct->sku,
            ]);

            return $report;
        }

        $rules = $virtualProduct->mediaInheritanceRules()->get()->keyBy('usage_type_id');
        $report['usage_type_count'] = $rules->count();

        if ($rules->isEmpty()) {
            // Häufigste Ursache für "es wird nichts vererbt": für keinen
            // Usage-Type (z.B. "Dokument"/PDF) wurde bislang eine
            // Medien-Vererbungsregel angelegt und aktiviert.
            Log::info('VirtualProductMediaSyncService: keine Medien-Vererbungsregeln konfiguriert — nichts zu synchronisieren', [
                'virtual_product_id' => $virtualProduct->id,
                'sku' => $virtualProduct->sku,
            ]);
        }

        DB::transaction(function () use ($virtualProduct, $definition, $rules, $memberIds, &$report) {
            $memberIds = collect($memberIds ?? $this->resolver->memberIds($definition));

            Log::info('VirtualProductMediaSyncService: sync gestartet', [
                'virtual_product_id' => $virtualProduct->id,
                'sku' => $virtualProduct->sku,
                'member_ids' => $memberIds->all(),
                'usage_type_ids' => $rules->keys()->all(),
            ]);

            $this->releaseFormerMembers($virtualProduct, $memberIds, $report);

            foreach ($memberIds as $memberId) {
                $conflictOwnerId = ProductMediaAssignment::where('product_id', $memberId)
                    ->whereNotNull('inherited_from_virtual_product_id')
                    ->where('inherited_from_virtual_product_id', '!=', $virtualProduct->id)
                    ->value('inherited_from_virtual_product_id');

                if ($conflictOwnerId) {
                    $owner = Product::find($conflictOwnerId);
                    $member = Product::find($memberId);
                    $reason = 'Bereits Mitglied von Cluster "' . ($owner->sku ?? $conflictOwnerId) . '" (Medien)';
                    $report['skipped_members'][] = [
                        'id' => $memberId,
                        'sku' => $member->sku ?? $memberId,
                        'reason' => $reason,
                    ];
                    Log::info('VirtualProductMediaSyncService: Mitglied übersprungen', [
                        'virtual_product_id' => $virtualProduct->id,
                        'member_id' => $memberId,
                        'reason' => $reason,
                    ]);
                    continue;
                }

                $report['member_count']++;
                $this->reconcileMember($virtualProduct, $memberId, $rules, $report);
            }

            Log::info('VirtualProductMediaSyncService: sync abgeschlossen', [
                'virtual_product_id' => $virtualProduct->id,
                'report' => $report,
            ]);
        });

        return $report;
    }

    /**
     * Entfernt vererbte Zuordnungen bei Produkten, die früher Mitglied
     * waren, es aber jetzt (laut aktueller Live-Auflösung) nicht mehr sind.
     */
    private function releaseFormerMembers(Product $virtualProduct, Collection $currentMemberIds, array &$report): void
    {
        $previouslySyncedMemberIds = ProductMediaAssignment::where('inherited_from_virtual_product_id', $virtualProduct->id)
            ->distinct()
            ->pluck('product_id');

        $releasedIds = $previouslySyncedMemberIds->diff($currentMemberIds);
        if ($releasedIds->isEmpty()) {
            return;
        }

        $removed = ProductMediaAssignment::where('inherited_from_virtual_product_id', $virtualProduct->id)
            ->whereIn('product_id', $releasedIds)
            ->delete();

        $report['assignments_removed'] += $removed;
        $report['released_members'] = Product::whereIn('id', $releasedIds)->pluck('sku', 'id')->all();
    }

    /**
     * @param  Collection<string, VirtualProductMediaInheritanceRule>  $rules
     */
    private function reconcileMember(Product $virtualProduct, string $memberId, Collection $rules, array &$report): void
    {
        // Regeln, die inzwischen entfernt wurden: zuvor vererbte Zuordnungen
        // für diese Usage-Types beim Mitglied ebenfalls entfernen.
        $previouslySyncedUsageTypeIds = ProductMediaAssignment::where('product_id', $memberId)
            ->where('inherited_from_virtual_product_id', $virtualProduct->id)
            ->pluck('usage_type_id')
            ->unique();

        $staleUsageTypeIds = $previouslySyncedUsageTypeIds->diff($rules->keys());
        if ($staleUsageTypeIds->isNotEmpty()) {
            $removed = ProductMediaAssignment::where('product_id', $memberId)
                ->where('inherited_from_virtual_product_id', $virtualProduct->id)
                ->whereIn('usage_type_id', $staleUsageTypeIds)
                ->delete();
            $report['assignments_removed'] += $removed;
        }

        foreach ($rules as $rule) {
            $this->reconcileUsageType($virtualProduct, $memberId, $rule, $report);
        }
    }

    private function reconcileUsageType(Product $virtualProduct, string $memberId, VirtualProductMediaInheritanceRule $rule, array &$report): void
    {
        $existingAssignments = ProductMediaAssignment::where('product_id', $memberId)
            ->where('usage_type_id', $rule->usage_type_id)
            ->get();

        $localAssignments = $existingAssignments->filter(
            fn (ProductMediaAssignment $a) => $a->inherited_from_virtual_product_id === null
        );

        if ($localAssignments->isNotEmpty()) {
            if ($rule->conflict_mode === VirtualProductMediaInheritanceRule::CONFLICT_KEEP_LOCAL) {
                $sourceCount = ProductMediaAssignment::where('product_id', $virtualProduct->id)
                    ->where('usage_type_id', $rule->usage_type_id)
                    ->count();
                $report['assignments_kept_local'] += $sourceCount;
                Log::debug('VirtualProductMediaSyncService: usage_type übersprungen (keep_local, lokale Zuordnung vorhanden)', [
                    'virtual_product_id' => $virtualProduct->id,
                    'member_id' => $memberId,
                    'usage_type_id' => $rule->usage_type_id,
                    'local_count' => $localAssignments->count(),
                    'source_count' => $sourceCount,
                ]);

                return;
            }

            ProductMediaAssignment::whereIn('id', $localAssignments->pluck('id'))->delete();
            $report['assignments_overridden'] += $localAssignments->count();
        }

        $sourceRows = ProductMediaAssignment::where('product_id', $virtualProduct->id)
            ->where('usage_type_id', $rule->usage_type_id)
            ->orderBy('sort_order')
            ->get();

        Log::debug('VirtualProductMediaSyncService: usage_type wird synchronisiert', [
            'virtual_product_id' => $virtualProduct->id,
            'member_id' => $memberId,
            'usage_type_id' => $rule->usage_type_id,
            'source_row_count' => $sourceRows->count(),
            'source_media_ids' => $sourceRows->pluck('media_id')->filter()->all(),
        ]);

        // Lokale Zuordnungen wurden oben ggf. bereits entfernt (force_override) —
        // die von diesem virtuellen Produkt vererbten Zeilen sind davon unberührt
        // und lassen sich aus $existingAssignments ableiten, statt sie erneut
        // aus der Datenbank zu laden.
        $existingOursByKey = $existingAssignments
            ->filter(fn (ProductMediaAssignment $a) => $a->inherited_from_virtual_product_id === $virtualProduct->id)
            ->keyBy(fn (ProductMediaAssignment $a) => $this->rowKey($a));

        $sourceKeys = [];

        foreach ($sourceRows as $source) {
            $key = $this->rowKey($source);
            $sourceKeys[] = $key;
            $existing = $existingOursByKey->get($key);

            $fields = [
                'media_id' => $source->media_id,
                'motif_id' => $source->motif_id,
                'sort_order' => $source->sort_order,
                'inherited_from_virtual_product_id' => $virtualProduct->id,
            ];

            if ($existing === null) {
                ProductMediaAssignment::create(array_merge($fields, [
                    'product_id' => $memberId,
                    'usage_type_id' => $rule->usage_type_id,
                ]));
                $report['assignments_created']++;
                continue;
            }

            $existing->update($fields);
            $report['assignments_updated']++;
        }

        foreach ($existingOursByKey->keys()->diff($sourceKeys) as $staleKey) {
            $existingOursByKey->get($staleKey)->delete();
            $report['assignments_removed']++;
        }
    }

    private function rowKey(ProductMediaAssignment $assignment): string
    {
        return $assignment->media_id !== null
            ? 'media:' . $assignment->media_id
            : 'motif:' . $assignment->motif_id;
    }
}
