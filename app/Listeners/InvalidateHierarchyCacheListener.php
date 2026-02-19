<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\UpdateSearchIndex;
use App\Models\HierarchyNode;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * InvalidateHierarchyCacheListener – Reagiert auf HierarchyNodeMoved Events.
 *
 * Verantwortlich für:
 * - Hierarchy-Tree-Cache invalidieren (hierarchy:{id}:tree)
 * - Alle Produkte im verschobenen Unterbaum re-indexieren
 *   (deren hierarchy_path hat sich geändert)
 */
class InvalidateHierarchyCacheListener implements ShouldQueue
{
    public string $queue = 'cache';

    /**
     * Event verarbeiten.
     *
     * Erwartet: $event->node (HierarchyNode Eloquent Model)
     */
    public function handle(object $event): void
    {
        $node = $event->node ?? null;

        if (!$node instanceof HierarchyNode) {
            Log::warning('InvalidateHierarchyCacheListener: No valid node in event', [
                'event_class' => get_class($event),
            ]);
            return;
        }

        // 1. Hierarchy-Cache invalidieren
        try {
            Cache::tags(['hierarchy:' . $node->hierarchy_id])->flush();
        } catch (\Throwable $e) {
            Log::warning('InvalidateHierarchyCacheListener: Cache flush failed', [
                'hierarchy_id' => $node->hierarchy_id,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Alle Produkte im Unterbaum re-indexieren
        $subtreeNodeIds = HierarchyNode::where('hierarchy_id', $node->hierarchy_id)
            ->where(function ($q) use ($node) {
                $q->where('path', 'LIKE', $node->path . '%')
                    ->orWhere('id', $node->id);
            })
            ->pluck('id');

        $productIds = Product::whereIn('master_hierarchy_node_id', $subtreeNodeIds)->pluck('id');

        foreach ($productIds as $productId) {
            try {
                Cache::tags(['product:' . $productId])->flush();
            } catch (\Throwable $e) {
                // Continue on cache error
            }
            dispatch(new UpdateSearchIndex($productId));
        }

        Log::info('InvalidateHierarchyCacheListener: Processed', [
            'node_id' => $node->id,
            'hierarchy_id' => $node->hierarchy_id,
            'affected_products' => $productIds->count(),
        ]);
    }
}
