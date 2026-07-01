<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseConsistencyController extends Controller
{
    /**
     * Run all consistency checks and return issues found.
     */
    public function check(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $checks = $this->runChecks();

        $totalIssues = collect($checks)->sum('count');

        return response()->json([
            'data' => [
                'checks' => $checks,
                'total_issues' => $totalIssues,
            ],
        ]);
    }

    /**
     * Fix a specific issue type by cleaning up orphaned records.
     */
    public function fix(string $issueType): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $deleted = match ($issueType) {
            'orphaned_attribute_values' => $this->fixOrphanedAttributeValues(),
            'orphaned_media_assignments' => $this->fixOrphanedMediaAssignments(),
            'orphaned_prices' => $this->fixOrphanedPrices(),
            'orphaned_relations' => $this->fixOrphanedRelations(),
            'orphaned_hierarchy_assignments' => $this->fixOrphanedHierarchyAssignments(),
            'orphaned_hierarchy_node_values' => $this->fixOrphanedHierarchyNodeValues(),
            'orphaned_workflow_tasks' => $this->fixOrphanedWorkflowTasks(),
            'orphaned_relation_attr_values' => $this->fixOrphanedRelationAttributeValues(),
            'orphaned_product_versions' => $this->fixOrphanedProductVersions(),
            'orphaned_import_job_errors' => $this->fixOrphanedImportJobErrors(),
            'orphaned_translation_job_items' => $this->fixOrphanedTranslationJobItems(),
            'orphaned_scheduled_actions' => $this->fixOrphanedScheduledActions(),
            'orphaned_media_attribute_values' => $this->fixOrphanedMediaAttributeValues(),
            'orphaned_cart_items' => $this->fixOrphanedCartItems(),
            'orphaned_conformance_results' => $this->fixOrphanedConformanceResults(),
            'stale_translatable_null_values' => $this->fixStaleTranslatableNull('product_attribute_values', ['product_id', 'output_hierarchy_id']),
            'stale_translatable_null_media_values' => $this->fixStaleTranslatableNull('media_attribute_values', ['media_id']),
            'stale_translatable_null_hierarchy_node_values' => $this->fixStaleTranslatableNull('hierarchy_node_attribute_values', ['hierarchy_node_id']),
            'stale_translatable_null_relation_values' => $this->fixStaleTranslatableNull('product_relation_attribute_values', ['product_relation_id']),
            'stale_translatable_null_output_hierarchy_values' => $this->fixStaleTranslatableNull('output_hierarchy_product_attribute_values', ['assignment_id']),
            default => null,
        };

        if ($deleted === null) {
            return response()->json([
                'error' => 'Unbekannter Prüfungstyp.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'issue_type' => $issueType,
                'deleted' => $deleted,
            ],
        ]);
    }

    // ─── Check methods ──────────────────────────────────────

    private function runChecks(): array
    {
        $checks = [];

        $checks[] = $this->checkOrphans(
            'orphaned_attribute_values',
            'Verwaiste Attributwerte',
            'Attributwerte, deren Produkt nicht mehr existiert.',
            'product_attribute_values',
            'product_id',
            'products',
        );

        $checks[] = $this->checkOrphans(
            'orphaned_media_assignments',
            'Verwaiste Medien-Zuweisungen',
            'Medien-Zuweisungen, deren Produkt nicht mehr existiert.',
            'product_media_assignments',
            'product_id',
            'products',
        );

        $checks[] = $this->checkOrphans(
            'orphaned_prices',
            'Verwaiste Preise',
            'Preise, deren Produkt nicht mehr existiert.',
            'product_prices',
            'product_id',
            'products',
        );

        $checks[] = $this->checkStaleTranslatableNull(
            'stale_translatable_null_values',
            'Verwaiste sprachneutrale Attributwerte',
            'Attributwerte mit language=NULL bei Attributen, die nachträglich auf "sprachabhängig" umgestellt wurden.',
            'product_attribute_values',
        );

        if (Schema::hasTable('media_attribute_values')) {
            $checks[] = $this->checkStaleTranslatableNull(
                'stale_translatable_null_media_values',
                'Verwaiste sprachneutrale Medien-Attributwerte',
                'Medien-Attributwerte mit language=NULL bei Attributen, die nachträglich auf "sprachabhängig" umgestellt wurden.',
                'media_attribute_values',
            );
        }

        if (Schema::hasTable('hierarchy_node_attribute_values')) {
            $checks[] = $this->checkStaleTranslatableNull(
                'stale_translatable_null_hierarchy_node_values',
                'Verwaiste sprachneutrale Hierarchie-Knoten-Attributwerte',
                'Hierarchie-Knoten-Attributwerte mit language=NULL bei Attributen, die nachträglich auf "sprachabhängig" umgestellt wurden.',
                'hierarchy_node_attribute_values',
            );
        }

        if (Schema::hasTable('product_relation_attribute_values')) {
            $checks[] = $this->checkStaleTranslatableNull(
                'stale_translatable_null_relation_values',
                'Verwaiste sprachneutrale Relationsattribut-Werte',
                'Relationsattribut-Werte mit language=NULL bei Attributen, die nachträglich auf "sprachabhängig" umgestellt wurden.',
                'product_relation_attribute_values',
            );
        }

        if (Schema::hasTable('output_hierarchy_product_attribute_values')) {
            $checks[] = $this->checkStaleTranslatableNull(
                'stale_translatable_null_output_hierarchy_values',
                'Verwaiste sprachneutrale Output-Hierarchie-Attributwerte',
                'Output-Hierarchie-Attributwerte mit language=NULL bei Attributen, die nachträglich auf "sprachabhängig" umgestellt wurden.',
                'output_hierarchy_product_attribute_values',
            );
        }

        if (Schema::hasTable('media_attribute_values')) {
            $checks[] = $this->checkOrphans(
                'orphaned_media_attribute_values',
                'Verwaiste Medien-Attributwerte',
                'Medien-Attributwerte, deren Medium nicht mehr existiert.',
                'media_attribute_values',
                'media_id',
                'media',
            );
        }

        if (Schema::hasTable('ecommerce_cart_items') && Schema::hasTable('ecommerce_carts')) {
            $checks[] = $this->checkOrphans(
                'orphaned_cart_items',
                'Verwaiste Warenkorb-Positionen',
                'Warenkorb-Positionen, deren Warenkorb nicht mehr existiert.',
                'ecommerce_cart_items',
                'cart_id',
                'ecommerce_carts',
            );
        }

        if (Schema::hasTable('product_conformance_results')) {
            $checks[] = $this->checkOrphans(
                'orphaned_conformance_results',
                'Verwaiste Konformitätsprüfungs-Ergebnisse',
                'Konformitätsprüfungs-Ergebnisse, deren Produkt nicht mehr existiert.',
                'product_conformance_results',
                'product_id',
                'products',
            );
        }

        $checks[] = $this->checkOrphanedRelations();

        $checks[] = $this->checkOrphans(
            'orphaned_hierarchy_assignments',
            'Verwaiste Hierarchie-Zuweisungen',
            'Produkt-Zuweisungen zu nicht mehr existierenden Hierarchie-Knoten.',
            'output_hierarchy_product_assignments',
            'hierarchy_node_id',
            'hierarchy_nodes',
        );

        if (Schema::hasTable('hierarchy_node_attribute_values')) {
            $checks[] = $this->checkOrphans(
                'orphaned_hierarchy_node_values',
                'Verwaiste Hierarchie-Knoten-Werte',
                'Attributwerte für nicht mehr existierende Hierarchie-Knoten.',
                'hierarchy_node_attribute_values',
                'hierarchy_node_id',
                'hierarchy_nodes',
            );
        }

        if (Schema::hasTable('workflow_tasks')) {
            $checks[] = $this->checkOrphans(
                'orphaned_workflow_tasks',
                'Verwaiste Workflow-Aufgaben',
                'Workflow-Aufgaben, deren Produkt nicht mehr existiert.',
                'workflow_tasks',
                'product_id',
                'products',
            );
        }

        if (Schema::hasTable('product_relation_attribute_values')) {
            $checks[] = $this->checkOrphans(
                'orphaned_relation_attr_values',
                'Verwaiste Relationsattribut-Werte',
                'Attributwerte, deren Produktrelation nicht mehr existiert.',
                'product_relation_attribute_values',
                'product_relation_id',
                'product_relations',
            );
        }

        if (Schema::hasTable('product_versions')) {
            $checks[] = $this->checkOrphans(
                'orphaned_product_versions',
                'Verwaiste Produkt-Versionen',
                'Versionierungsdaten für gelöschte Produkte.',
                'product_versions',
                'product_id',
                'products',
            );
        }

        if (Schema::hasTable('import_job_errors') && Schema::hasTable('import_jobs')) {
            $checks[] = $this->checkOrphans(
                'orphaned_import_job_errors',
                'Verwaiste Import-Fehler',
                'Import-Fehler, deren Import-Job nicht mehr existiert.',
                'import_job_errors',
                'import_job_id',
                'import_jobs',
            );
        }

        if (Schema::hasTable('translation_job_items') && Schema::hasTable('translation_jobs')) {
            $checks[] = $this->checkOrphans(
                'orphaned_translation_job_items',
                'Verwaiste Übersetzungs-Einträge',
                'Übersetzungseinträge, deren Übersetzungsjob nicht mehr existiert.',
                'translation_job_items',
                'translation_job_id',
                'translation_jobs',
            );
        }

        if (Schema::hasTable('scheduled_actions')) {
            $checks[] = $this->checkOrphans(
                'orphaned_scheduled_actions',
                'Verwaiste geplante Aktionen',
                'Geplante Aktionen, deren Produkt nicht mehr existiert.',
                'scheduled_actions',
                'product_id',
                'products',
            );
        }

        return $checks;
    }

    private function checkOrphans(
        string $key,
        string $label,
        string $description,
        string $table,
        string $foreignKey,
        string $parentTable,
    ): array {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable)) {
            return [
                'key' => $key,
                'label' => $label,
                'description' => $description,
                'count' => 0,
                'status' => 'skipped',
            ];
        }

        $count = DB::table($table)
            ->leftJoin($parentTable, "{$table}.{$foreignKey}", '=', "{$parentTable}.id")
            ->whereNotNull("{$table}.{$foreignKey}")
            ->whereNull("{$parentTable}.id")
            ->count();

        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'count' => $count,
            'status' => $count > 0 ? 'issue' : 'ok',
        ];
    }

    /**
     * Prüft eine EAV-Werte-Tabelle (product_attribute_values, media_attribute_values, ...) auf
     * language=NULL-Zeilen bei Attributen, die nachträglich auf "sprachabhängig" umgestellt wurden.
     * Der Unique-Index behandelt NULL als eigenständigen Wert und verhindert nicht, dass eine solche
     * Altlast neben einer neu angelegten sprachspezifischen Zeile bestehen bleibt.
     */
    private function checkStaleTranslatableNull(string $key, string $label, string $description, string $table): array
    {
        $count = DB::table($table)
            ->join('attributes', "{$table}.attribute_id", '=', 'attributes.id')
            ->where('attributes.is_translatable', true)
            ->whereNull("{$table}.language")
            ->count();

        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'count' => $count,
            'status' => $count > 0 ? 'issue' : 'ok',
        ];
    }

    private function checkOrphanedRelations(): array
    {
        $count = 0;

        if (Schema::hasTable('product_relations') && Schema::hasTable('products')) {
            // Source product missing
            $count += DB::table('product_relations')
                ->leftJoin('products', 'product_relations.source_product_id', '=', 'products.id')
                ->whereNull('products.id')
                ->count();

            // Target product missing
            $count += DB::table('product_relations')
                ->leftJoin('products', 'product_relations.target_product_id', '=', 'products.id')
                ->whereNull('products.id')
                ->count();
        }

        return [
            'key' => 'orphaned_relations',
            'label' => 'Verwaiste Produktrelationen',
            'description' => 'Relationen, deren Quell- oder Zielprodukt nicht mehr existiert.',
            'count' => $count,
            'status' => $count > 0 ? 'issue' : 'ok',
        ];
    }

    // ─── Fix methods ────────────────────────────────────────

    private function deleteOrphans(string $table, string $foreignKey, string $parentTable): int
    {
        $orphanIds = DB::table($table)
            ->leftJoin($parentTable, "{$table}.{$foreignKey}", '=', "{$parentTable}.id")
            ->whereNotNull("{$table}.{$foreignKey}")
            ->whereNull("{$parentTable}.id")
            ->pluck("{$table}.id");

        if ($orphanIds->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn('id', $orphanIds)->delete();
    }

    private function fixOrphanedAttributeValues(): int
    {
        return $this->deleteOrphans('product_attribute_values', 'product_id', 'products');
    }

    private function fixOrphanedMediaAssignments(): int
    {
        return $this->deleteOrphans('product_media_assignments', 'product_id', 'products');
    }

    private function fixOrphanedPrices(): int
    {
        return $this->deleteOrphans('product_prices', 'product_id', 'products');
    }

    private function fixOrphanedRelations(): int
    {
        $deleted = 0;

        // Source product missing
        $ids = DB::table('product_relations')
            ->leftJoin('products', 'product_relations.source_product_id', '=', 'products.id')
            ->whereNull('products.id')
            ->pluck('product_relations.id');

        if ($ids->isNotEmpty()) {
            $deleted += DB::table('product_relations')->whereIn('id', $ids)->delete();
        }

        // Target product missing
        $ids = DB::table('product_relations')
            ->leftJoin('products', 'product_relations.target_product_id', '=', 'products.id')
            ->whereNull('products.id')
            ->pluck('product_relations.id');

        if ($ids->isNotEmpty()) {
            $deleted += DB::table('product_relations')->whereIn('id', $ids)->delete();
        }

        return $deleted;
    }

    private function fixOrphanedHierarchyAssignments(): int
    {
        return $this->deleteOrphans('output_hierarchy_product_assignments', 'hierarchy_node_id', 'hierarchy_nodes');
    }

    private function fixOrphanedHierarchyNodeValues(): int
    {
        return $this->deleteOrphans('hierarchy_node_attribute_values', 'hierarchy_node_id', 'hierarchy_nodes');
    }

    private function fixOrphanedWorkflowTasks(): int
    {
        return $this->deleteOrphans('workflow_tasks', 'product_id', 'products');
    }

    private function fixOrphanedRelationAttributeValues(): int
    {
        return $this->deleteOrphans('product_relation_attribute_values', 'product_relation_id', 'product_relations');
    }

    private function fixOrphanedProductVersions(): int
    {
        return $this->deleteOrphans('product_versions', 'product_id', 'products');
    }

    private function fixOrphanedImportJobErrors(): int
    {
        return $this->deleteOrphans('import_job_errors', 'import_job_id', 'import_jobs');
    }

    private function fixOrphanedTranslationJobItems(): int
    {
        return $this->deleteOrphans('translation_job_items', 'translation_job_id', 'translation_jobs');
    }

    private function fixOrphanedScheduledActions(): int
    {
        return $this->deleteOrphans('scheduled_actions', 'product_id', 'products');
    }

    private function fixOrphanedMediaAttributeValues(): int
    {
        return $this->deleteOrphans('media_attribute_values', 'media_id', 'media');
    }

    private function fixOrphanedCartItems(): int
    {
        return $this->deleteOrphans('ecommerce_cart_items', 'cart_id', 'ecommerce_carts');
    }

    private function fixOrphanedConformanceResults(): int
    {
        return $this->deleteOrphans('product_conformance_results', 'product_id', 'products');
    }

    /**
     * Existiert bereits eine sprachspezifische Zeile für dieselbe Werte-Instanz, wird die
     * language=NULL-Zeile (Duplikat) gelöscht. Andernfalls wird sie auf die Standardsprache
     * migriert, damit der Wert erhalten bleibt.
     *
     * @param array<int, string> $scopeColumns Spalten, die zusammen mit attribute_id + multiplied_index
     *                                         eine Werte-Instanz identifizieren (z.B. ['product_id', 'output_hierarchy_id']).
     */
    private function fixStaleTranslatableNull(string $table, array $scopeColumns): int
    {
        $defaultLanguage = 'de';

        $rows = DB::table($table)
            ->join('attributes', "{$table}.attribute_id", '=', 'attributes.id')
            ->where('attributes.is_translatable', true)
            ->whereNull("{$table}.language")
            ->select("{$table}.*")
            ->get();

        $affected = 0;

        foreach ($rows as $row) {
            $query = DB::table($table)
                ->where('attribute_id', $row->attribute_id)
                ->where('multiplied_index', $row->multiplied_index)
                ->whereNotNull('language');

            foreach ($scopeColumns as $column) {
                $query->where($column, $row->{$column});
            }

            if ($query->exists()) {
                DB::table($table)->where('id', $row->id)->delete();
            } else {
                DB::table($table)->where('id', $row->id)->update(['language' => $defaultLanguage]);
            }

            $affected++;
        }

        return $affected;
    }
}
