<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateSearchIndex;
use App\Models\HierarchyNode;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * HierarchyNodeObserver – reagiert auf Änderungen an Hierarchieknoten.
 *
 * Verantwortlich für:
 * - Hierarchy-Tree-Cache invalidieren (hierarchy:{id}:tree)
 * - Knoten-Attribut-Cache invalidieren (hierarchy:{id}:node:{nid}:attrs)
 * - Produkte im Unterbaum re-indexieren (bei Verschiebung)
 */
class HierarchyNodeObserver
{
    /**
     * Knoten wurde erstellt.
     * → Baum-Cache invalidieren.
     */
    public function created(HierarchyNode $node): void
    {
        $this->invalidateHierarchyCache($node);

        Log::debug('HierarchyNodeObserver::created', [
            'node_id' => $node->id,
            'hierarchy_id' => $node->hierarchy_id,
        ]);
    }

    /**
     * Knoten wurde aktualisiert.
     * → Baum-Cache + Attribut-Cache invalidieren.
     * → Bei path-Änderung (Verschiebung): Alle Produkte im Unterbaum re-indexieren.
     */
    public function updated(HierarchyNode $node): void
    {
        $this->invalidateHierarchyCache($node);

        // Knoten-Attribut-Cache invalidieren
        $this->invalidateNodeAttributeCache($node);

        // Wenn sich der Pfad geändert hat (Knoten wurde verschoben)
        if ($node->isDirty('path') || $node->isDirty('parent_node_id')) {
            $this->reindexProductsInSubtree($node);
        }

        Log::debug('HierarchyNodeObserver::updated', [
            'node_id' => $node->id,
            'dirty' => $node->getDirty(),
        ]);
    }

    /**
     * Knoten wird gelöscht.
     * → Baum-Cache invalidieren.
     */
    public function deleted(HierarchyNode $node): void
    {
        $this->invalidateHierarchyCache($node);

        Log::debug('HierarchyNodeObserver::deleted', ['node_id' => $node->id]);
    }

    /**
     * Gesamten Baum-Cache für die Hierarchie invalidieren.
     */
    private function invalidateHierarchyCache(HierarchyNode $node): void
    {
        try {
            Cache::tags(['hierarchy:' . $node->hierarchy_id])->flush();
        } catch (\Throwable $e) {
            Log::warning('HierarchyNodeObserver: Cache flush failed', [
                'hierarchy_id' => $node->hierarchy_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Attribut-Cache für einen spezifischen Knoten invalidieren.
     */
    private function invalidateNodeAttributeCache(HierarchyNode $node): void
    {
        try {
            $cacheKey = "hierarchy:{$node->hierarchy_id}:node:{$node->id}:attrs";
            Cache::forget($cacheKey);
        } catch (\Throwable $e) {
            Log::warning('HierarchyNodeObserver: Node attr cache flush failed', [
                'node_id' => $node->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bei Verschiebung eines Knotens: Alle Produkte, deren
     * master_hierarchy_node_id in diesem Unterbaum liegt,
     * müssen im Search-Index aktualisiert werden (neuer hierarchy_path).
     */
    private function reindexProductsInSubtree(HierarchyNode $node): void
    {
        // Alle Knoten-IDs im Unterbaum (inkl. aktueller Knoten)
        $subtreeNodeIds = HierarchyNode::where('hierarchy_id', $node->hierarchy_id)
            ->where('path', 'LIKE', $node->path . '%')
            ->pluck('id')
            ->push($node->id)
            ->unique();

        // Alle Produkte in diesen Knoten
        $productIds = Product::whereIn('master_hierarchy_node_id', $subtreeNodeIds)->pluck('id');

        foreach ($productIds as $productId) {
            try {
                Cache::tags(['product:' . $productId])->flush();
            } catch (\Throwable $e) {
                // Log but continue
            }
            dispatch(new UpdateSearchIndex($productId))->afterCommit();
        }

        Log::info('HierarchyNodeObserver: Reindexing subtree products', [
            'node_id' => $node->id,
            'product_count' => $productIds->count(),
        ]);
    }
}
