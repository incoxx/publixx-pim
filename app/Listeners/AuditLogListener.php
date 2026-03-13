<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AttributeValuesChanged;
use App\Events\ProductCreated;
use App\Events\ProductDeleted;
use App\Events\ProductUpdated;
use App\Jobs\WriteAuditLog;

/**
 * Synchronous listener that captures request context immediately,
 * then dispatches WriteAuditLog as a queued job.
 */
class AuditLogListener
{
    public function handle(object $event): void
    {
        $request = request();
        $userId = $request->user()?->id;
        $ip = $request->ip();
        $ua = $request->userAgent();

        match (true) {
            $event instanceof ProductCreated => $this->handleCreated($event, $userId, $ip, $ua),
            $event instanceof ProductUpdated => $this->handleUpdated($event, $userId, $ip, $ua),
            $event instanceof ProductDeleted => $this->handleDeleted($event, $userId, $ip, $ua),
            $event instanceof AttributeValuesChanged => $this->handleAttributeValues($event, $userId, $ip, $ua),
            default => null,
        };
    }

    private function handleCreated(ProductCreated $event, ?string $userId, ?string $ip, ?string $ua): void
    {
        $product = $event->product;

        WriteAuditLog::dispatch(
            auditableType: 'Product',
            auditableId: $product->id,
            action: 'created',
            oldValues: null,
            newValues: $product->only(['id', 'sku', 'name', 'ean', 'status', 'product_type_id', 'product_type_ref']),
            userId: $userId,
            ipAddress: $ip,
            userAgent: $ua,
        )->afterCommit();
    }

    private function handleUpdated(ProductUpdated $event, ?string $userId, ?string $ip, ?string $ua): void
    {
        $product = $event->product;
        $changes = $product->getChanges();

        // Remove timestamps from tracked changes
        unset($changes['updated_at'], $changes['created_at']);

        if (empty($changes)) {
            return;
        }

        $oldValues = collect($product->getOriginal())
            ->only(array_keys($changes))
            ->toArray();

        WriteAuditLog::dispatch(
            auditableType: 'Product',
            auditableId: $product->id,
            action: 'updated',
            oldValues: $oldValues,
            newValues: $changes,
            userId: $userId,
            ipAddress: $ip,
            userAgent: $ua,
        )->afterCommit();
    }

    private function handleDeleted(ProductDeleted $event, ?string $userId, ?string $ip, ?string $ua): void
    {
        WriteAuditLog::dispatch(
            auditableType: 'Product',
            auditableId: $event->productId,
            action: 'deleted',
            oldValues: $event->snapshot,
            newValues: null,
            userId: $userId,
            ipAddress: $ip,
            userAgent: $ua,
        )->afterCommit();
    }

    private function handleAttributeValues(AttributeValuesChanged $event, ?string $userId, ?string $ip, ?string $ua): void
    {
        WriteAuditLog::dispatch(
            auditableType: 'Product',
            auditableId: $event->productId,
            action: 'attributes_changed',
            oldValues: null,
            newValues: [
                'attribute_ids' => $event->attributeIds,
                'output_hierarchy_id' => $event->outputHierarchyId,
            ],
            userId: $userId,
            ipAddress: $ip,
            userAgent: $ua,
        )->afterCommit();
    }
}
