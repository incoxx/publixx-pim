<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Connectors\Canva\CanvaAssetService;
use App\Services\Connectors\Canva\CanvaAuthService;
use App\Services\Connectors\Canva\CanvaConnector;
use App\Services\Connectors\Canva\CanvaDataService;
use App\Services\Connectors\ClaudeAI\ClaudeAIConnector;
use App\Services\Connectors\ClaudeAI\ClaudeAITextService;
use App\Services\Connectors\Cloudinary\CloudinaryAssetService;
use App\Services\Connectors\Cloudinary\CloudinaryConnector;
use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\DeepL\DeepLConnector;
use App\Services\Connectors\DeepL\DeepLTranslationService;
use App\Services\Connectors\Shopware\ShopwareAuthService;
use App\Services\Connectors\Shopware\ShopwareCategoryService;
use App\Services\Connectors\Shopware\ShopwareConnector;
use App\Services\Connectors\Shopware\ShopwareMediaService;
use App\Services\Connectors\Shopware\ShopwareProductService;
use App\Models\Setting;
use Illuminate\Support\ServiceProvider;

class ConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge DB-stored credentials into config (overrides .env values)
        $this->app->booted(function () {
            try {
                $dbCredentials = Setting::getPayload('connector_credentials');
            } catch (\Throwable) {
                $dbCredentials = null; // Table may not exist during migrations
            }

            if ($dbCredentials) {
                foreach ($dbCredentials as $connector => $fields) {
                    foreach ($fields as $key => $value) {
                        if (! empty($value)) {
                            config(["connectors.{$connector}.{$key}" => $value]);
                        }
                    }
                }

                // Sync shared keys into TMS provider config
                // DeepL: used by both connector system and TMS
                if (! empty($dbCredentials['deepl']['api_key'])) {
                    config(['tms.providers.deepl.api_key' => $dbCredentials['deepl']['api_key']]);
                }
                // Google Translate: TMS only
                if (! empty($dbCredentials['google_translate']['api_key'])) {
                    config(['tms.providers.google.api_key' => $dbCredentials['google_translate']['api_key']]);
                }
                // Anthropic/Claude: TMS translation provider
                if (! empty($dbCredentials['anthropic_tms']['api_key'])) {
                    config(['tms.providers.claude.api_key' => $dbCredentials['anthropic_tms']['api_key']]);
                }
                if (! empty($dbCredentials['anthropic_tms']['model'])) {
                    config(['tms.providers.claude.model' => $dbCredentials['anthropic_tms']['model']]);
                }
                // OpenAI: TMS translation provider
                if (! empty($dbCredentials['openai_tms']['api_key'])) {
                    config(['tms.providers.openai.api_key' => $dbCredentials['openai_tms']['api_key']]);
                }
                if (! empty($dbCredentials['openai_tms']['model'])) {
                    config(['tms.providers.openai.model' => $dbCredentials['openai_tms']['model']]);
                }
            }
        });

        $this->app->singleton(ConnectorRegistry::class);

        // Canva
        $this->app->singleton(CanvaAuthService::class);
        $this->app->singleton(CanvaAssetService::class);
        $this->app->singleton(CanvaDataService::class);
        $this->app->singleton(CanvaConnector::class);

        // DeepL
        $this->app->singleton(DeepLTranslationService::class);
        $this->app->singleton(DeepLConnector::class);

        // Shopware
        $this->app->singleton(ShopwareAuthService::class);
        $this->app->singleton(ShopwareProductService::class);
        $this->app->singleton(ShopwareMediaService::class);
        $this->app->singleton(ShopwareCategoryService::class);
        $this->app->singleton(ShopwareConnector::class);

        // Cloudinary
        $this->app->singleton(CloudinaryAssetService::class);
        $this->app->singleton(CloudinaryConnector::class);

        // Claude AI
        $this->app->singleton(ClaudeAITextService::class);
        $this->app->singleton(ClaudeAIConnector::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(ConnectorRegistry::class);

        $registry->register($this->app->make(CanvaConnector::class));
        $registry->register($this->app->make(DeepLConnector::class));
        $registry->register($this->app->make(ShopwareConnector::class));
        $registry->register($this->app->make(CloudinaryConnector::class));
        $registry->register($this->app->make(ClaudeAIConnector::class));
    }
}
