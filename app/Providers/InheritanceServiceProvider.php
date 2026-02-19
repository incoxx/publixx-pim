<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AttributeValuesChanged;
use App\Events\HierarchyAttributeChanged;
use App\Events\HierarchyNodeMoved;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Services\Inheritance\AttributeValueResolver;
use App\Services\Inheritance\HierarchyInheritanceService;
use App\Services\Inheritance\VariantInheritanceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InheritanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register as singletons for efficient reuse within a request
        $this->app->singleton(HierarchyInheritanceService::class);
        $this->app->singleton(VariantInheritanceService::class);

        $this->app->singleton(AttributeValueResolver::class, function ($app) {
            return new AttributeValueResolver(
                $app->make(HierarchyInheritanceService::class),
                $app->make(VariantInheritanceService::class),
            );
        });
    }

    /**
     * Bootstrap services — register event listeners for cache invalidation.
     */
    public function boot(): void
    {
        $this->registerEventListeners();
    }

    /**
     * Register event listeners for inheritance cache invalidation.
     */
    private function registerEventListeners(): void
    {
        // When attribute values change on a product → invalidate variant caches
        Event::listen(AttributeValuesChanged::class, function (AttributeValuesChanged $event) {
            $product = Product::find($event->productId);
            if (!$product) {
                return;
            }

            // If this product has variants, invalidate their caches too
            $variantIds = Product::where('parent_product_id', $product->id)->pluck('id');
            foreach ($variantIds as $variantId) {
                Cache::tags(["product:{$variantId}"])->flush();
            }
        });

        // When a hierarchy node is moved → invalidate all affected caches
        Event::listen(HierarchyNodeMoved::class, function (HierarchyNodeMoved $event) {
            /** @var HierarchyInheritanceService $hierarchyService */
            $hierarchyService = app(HierarchyInheritanceService::class);
            $hierarchyService->invalidateNodeCache($event->node);
        });

        // When a hierarchy node attribute assignment changes → invalidate descendants + products
        Event::listen(HierarchyAttributeChanged::class, function (HierarchyAttributeChanged $event) {
            $node = HierarchyNode::find($event->nodeId);
            if (!$node) {
                return;
            }

            /** @var HierarchyInheritanceService $hierarchyService */
            $hierarchyService = app(HierarchyInheritanceService::class);
            $hierarchyService->invalidateNodeCache($node);
        });
    }
}
