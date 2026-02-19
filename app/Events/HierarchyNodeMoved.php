<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\HierarchyNode;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a hierarchy node is moved to a new parent.
 *
 * Consumers:
 * - Agent 4 (Inheritance): Recalculates effective attributes for all products under the moved node
 * - Agent 9 (Performance): Updates search index for affected products
 */
class HierarchyNodeMoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param HierarchyNode $node          The moved node
     * @param string|null   $oldParentId   Previous parent node ID
     * @param string        $oldPath       Previous materialized path
     */
    public function __construct(
        public readonly HierarchyNode $node,
        public readonly ?string $oldParentId = null,
        public readonly string $oldPath = '',
    ) {}
}
