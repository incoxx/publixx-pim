<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Pql\FuzzyMatcher;
use App\Services\Pql\PhoneticMatcher;
use App\Services\Pql\PqlExecutor;
use App\Services\Pql\PqlParser;
use App\Services\Pql\PqlSqlGenerator;
use App\Services\Pql\PqlValidator;
use Illuminate\Support\ServiceProvider;

/**
 * Registers all PQL Engine services in the Laravel container.
 *
 * Registration in config/app.php:
 *   'providers' => [
 *       // ...
 *       App\Providers\PqlServiceProvider::class,
 *   ],
 */
final class PqlServiceProvider extends ServiceProvider
{
    /**
     * All PQL services are singletons (stateless or reset per request).
     */
    public function register(): void
    {
        $this->app->singleton(PqlParser::class);
        $this->app->singleton(PqlValidator::class);
        $this->app->singleton(PqlSqlGenerator::class);
        $this->app->singleton(FuzzyMatcher::class);
        $this->app->singleton(PhoneticMatcher::class);

        $this->app->singleton(PqlExecutor::class, function ($app) {
            return new PqlExecutor(
                parser: $app->make(PqlParser::class),
                validator: $app->make(PqlValidator::class),
                generator: $app->make(PqlSqlGenerator::class),
                fuzzyMatcher: $app->make(FuzzyMatcher::class),
            );
        });
    }

    public function boot(): void
    {
        // No boot actions needed
    }
}
