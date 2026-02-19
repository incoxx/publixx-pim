<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when attribute values change on a product.
 *
 * Consumers:
 * - Agent 9 (Performance): Updates search index
 * - Agent 4 (Inheritance): Propagates to variants
 */
class AttributeValuesChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string   $productId    The product whose values changed
     * @param string[] $attributeIds The attribute IDs that changed
     */
    public function __construct(
        public readonly string $productId,
        public readonly array $attributeIds,
    ) {}
}
