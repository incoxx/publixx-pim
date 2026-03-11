<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AttributeValuesChanged;
use App\Services\ComputedAttributeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * ComputeCompositeValuesListener – Berechnet Composite-Attribute
 * mit Expression neu, wenn sich Kind-Attributwerte ändern.
 *
 * Reagiert auf AttributeValuesChanged Events und delegiert
 * an ComputedAttributeService.
 */
class ComputeCompositeValuesListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly ComputedAttributeService $computedService,
    ) {}

    public function handle(AttributeValuesChanged $event): void
    {
        Log::debug('ComputeCompositeValuesListener: Processing', [
            'product_id' => $event->productId,
            'attribute_ids' => $event->attributeIds,
        ]);

        $this->computedService->recomputeForChangedAttributes(
            $event->productId,
            $event->attributeIds,
        );
    }
}
