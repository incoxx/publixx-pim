<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AttributeValuesChanged;
use App\Events\HierarchyNodeMoved;
use App\Events\ImportCompleted;
use App\Events\ProductCreated;
use App\Events\ProductDeleted;
use App\Events\ProductUpdated;
use App\Listeners\CascadeInvalidationListener;
use App\Listeners\InvalidateHierarchyCacheListener;
use App\Listeners\InvalidateProductCacheListener;
use App\Listeners\UpdateSearchIndexListener;
use App\Listeners\WarmupCacheListener;
use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Observers\AttributeObserver;
use App\Observers\AttributeValueObserver;
use App\Observers\HierarchyNodeObserver;
use App\Observers\ProductObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * EventServiceProvider – Zentrale Zuordnung von Events zu Listenern.
 *
 * Registriert:
 * 1. Model Observers (direkte Eloquent-Hooks)
 * 2. Event-Listener (Event-basierte Kommunikation zwischen Agenten)
 *
 * Events kommen von:
 * - Agent 4 (Vererbung): AttributeValuesChanged, HierarchyNodeMoved
 * - Agent 6 (Import): ImportCompleted
 * - Agent 3 (API): ProductCreated, ProductUpdated, ProductDeleted
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Event-Listener-Zuordnung.
     *
     * Jedes Event wird von einem oder mehreren Listenern verarbeitet.
     * Listener laufen als Queue-Jobs (ShouldQueue).
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Produkt-Lifecycle-Events
        ProductCreated::class => [
            UpdateSearchIndexListener::class,
        ],

        ProductUpdated::class => [
            InvalidateProductCacheListener::class,
            UpdateSearchIndexListener::class,
        ],

        ProductDeleted::class => [
            InvalidateProductCacheListener::class,
            // RemoveSearchIndex wird via InvalidateProductCacheListener dispatcht
        ],

        // Attributwert-Änderungen (von Vererbungs-Agent oder API)
        AttributeValuesChanged::class => [
            CascadeInvalidationListener::class,
        ],

        // Hierarchie-Verschiebung (von Vererbungs-Agent)
        HierarchyNodeMoved::class => [
            InvalidateHierarchyCacheListener::class,
        ],

        // Import abgeschlossen (von Import-Agent)
        ImportCompleted::class => [
            WarmupCacheListener::class,
        ],
    ];

    /**
     * Model Observer registrieren.
     *
     * Observers reagieren direkt auf Eloquent-Lifecycle-Hooks
     * (created, updated, deleted, saved) und sind komplementär
     * zu den Event-Listenern.
     */
    public function boot(): void
    {
        parent::boot();

        Product::observe(ProductObserver::class);
        HierarchyNode::observe(HierarchyNodeObserver::class);
        ProductAttributeValue::observe(AttributeValueObserver::class);
        Attribute::observe(AttributeObserver::class);
    }

    /**
     * Events und Listener auto-discovern.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
