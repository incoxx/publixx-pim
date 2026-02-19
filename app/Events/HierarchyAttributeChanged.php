<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when an attribute assignment on a hierarchy node changes.
 * This includes: adding, removing, or modifying attribute assignments.
 *
 * Consumers:
 * - Agent 4 (Inheritance): Invalidates cached effective attributes, propagates to products
 * - Agent 9 (Performance): Updates search index for affected products
 */
class HierarchyAttributeChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $nodeId      The hierarchy node whose attribute assignment changed
     * @param string $attributeId The attribute that was added/removed/modified
     * @param string $changeType  'added', 'removed', or 'modified'
     */
    public function __construct(
        public readonly string $nodeId,
        public readonly string $attributeId,
        public readonly string $changeType = 'modified',
    ) {}
}
