<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Export\DatasetBuilder;
use App\Services\Export\ExportService;
use App\Services\Export\MappingResolver;
use App\Services\Export\PublixxDatasetService;
use App\Events\ProductUpdated;
use App\Events\ProductDeleted;
use App\Events\AttributeValuesChanged;
use Illuminate\Support\ServiceProvider;

class ExportServiceProvider extends ServiceProvider
{
    /**
     * Register export services.
     */
    public function register(): void
    {
        $this->app->singleton(MappingResolver::class);

        $this->app->singleton(DatasetBuilder::class, function ($app) {
            return new DatasetBuilder(
                $app->make(MappingResolver::class)
            );
        });

        $this->app->singleton(ExportService::class, function ($app) {
            return new ExportService(
                $app->make(DatasetBuilder::class),
                $app->make(MappingResolver::class),
            );
        });

        $this->app->singleton(PublixxDatasetService::class, function ($app) {
            return new PublixxDatasetService(
                $app->make(ExportService::class),
                $app->make(DatasetBuilder::class),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Listen for product/mapping changes to invalidate export cache
        $this->registerCacheInvalidationListeners();
    }

    /**
     * Register event listeners for cache invalidation.
     */
    protected function registerCacheInvalidationListeners(): void
    {
        $events = $this->app['events'];

        // Product events → invalidate product cache
        $events->listen(ProductUpdated::class, function ($event) {
            $this->app->make(ExportService::class)
                ->invalidateProductCache($event->product->id);
        });

        $events->listen(ProductDeleted::class, function ($event) {
            $this->app->make(ExportService::class)
                ->invalidateProductCache($event->productId);
        });

        $events->listen(AttributeValuesChanged::class, function ($event) {
            $this->app->make(ExportService::class)
                ->invalidateProductCache($event->productId);
        });
    }
}
