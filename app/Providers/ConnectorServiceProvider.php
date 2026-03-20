<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Connectors\Canva\CanvaAssetService;
use App\Services\Connectors\Canva\CanvaAuthService;
use App\Services\Connectors\Canva\CanvaConnector;
use App\Services\Connectors\Canva\CanvaDataService;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\Support\ServiceProvider;

class ConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class);

        // Canva-Services
        $this->app->singleton(CanvaAuthService::class);
        $this->app->singleton(CanvaAssetService::class);
        $this->app->singleton(CanvaDataService::class);
        $this->app->singleton(CanvaConnector::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(ConnectorRegistry::class);

        // Canva Connector registrieren
        $registry->register($this->app->make(CanvaConnector::class));
    }
}
