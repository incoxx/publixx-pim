<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Connectors\ClaudeAI\ClaudeAIConnector;
use App\Services\Connectors\ClaudeAI\ClaudeAITextService;
use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\DeepL\DeepLConnector;
use App\Services\Connectors\DeepL\DeepLTranslationService;
use App\Services\Connectors\AnyPim\AnyPimAuthService;
use App\Services\Connectors\AnyPim\AnyPimCategoryService;
use App\Services\Connectors\AnyPim\AnyPimConfigService;
use App\Services\Connectors\AnyPim\AnyPimConnector;
use App\Services\Connectors\AnyPim\AnyPimMediaService;
use App\Services\Connectors\AnyPim\AnyPimProductService;
use App\Services\Connectors\Shopware\ShopwareAuthService;
use App\Services\Connectors\Shopware\ShopwareCategoryService;
use App\Services\Connectors\Shopware\ShopwareConnector;
use App\Services\Connectors\Shopware\ShopwareMediaService;
use App\Services\Connectors\Shopware\ShopwareProductService;
use App\Services\Connectors\Shopware\ShopwarePropertyService;
use App\Services\Connectors\Shopify\ShopifyAuthService;
use App\Services\Connectors\Shopify\ShopifyCategoryService;
use App\Services\Connectors\Shopify\ShopifyChecksumService;
use App\Services\Connectors\Shopify\ShopifyConnector;
use App\Services\Connectors\Shopify\ShopifyMediaService;
use App\Services\Connectors\Shopify\ShopifyMetafieldService;
use App\Services\Connectors\Shopify\ShopifyProductService;
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

        // DeepL
        $this->app->singleton(DeepLTranslationService::class);
        $this->app->singleton(DeepLConnector::class);

        // Shopware
        $this->app->singleton(ShopwareAuthService::class);
        $this->app->singleton(ShopwareProductService::class);
        $this->app->singleton(ShopwareMediaService::class);
        $this->app->singleton(ShopwareCategoryService::class);
        $this->app->singleton(ShopwarePropertyService::class);
        $this->app->singleton(ShopwareConnector::class);

        // Shopify
        $this->app->singleton(ShopifyAuthService::class);
        $this->app->singleton(ShopifyProductService::class);
        $this->app->singleton(ShopifyMediaService::class);
        $this->app->singleton(ShopifyCategoryService::class);
        $this->app->singleton(ShopifyMetafieldService::class);
        $this->app->singleton(ShopifyChecksumService::class);
        $this->app->singleton(ShopifyConnector::class);

        // Claude AI
        $this->app->singleton(ClaudeAITextService::class);
        $this->app->singleton(ClaudeAIConnector::class);

        // anyPIM (bidirektionaler Sync)
        $this->app->singleton(AnyPimAuthService::class);
        $this->app->singleton(AnyPimProductService::class);
        $this->app->singleton(AnyPimMediaService::class);
        $this->app->singleton(AnyPimCategoryService::class);
        $this->app->singleton(AnyPimConfigService::class);
        $this->app->singleton(AnyPimConnector::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(ConnectorRegistry::class);

        $connectorClasses = [
            DeepLConnector::class,
            ShopwareConnector::class,
            ShopifyConnector::class,
            ClaudeAIConnector::class,
            AnyPimConnector::class,
        ];

        foreach ($connectorClasses as $class) {
            try {
                $registry->register($this->app->make($class));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::channel('connectors')->error(
                    "Connector-Registrierung fehlgeschlagen: {$class}",
                    ['error' => $e->getMessage()],
                );
            }
        }
    }
}
