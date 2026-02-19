<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\Product;
use App\Models\User;
use App\Policies\AttributePolicy;
use App\Policies\ExportPolicy;
use App\Policies\HierarchyPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ─── Policy Registration (Agent 2) ───────────────────────────
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(Hierarchy::class, HierarchyPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // ExportPolicy — no model, registered as Gates
        Gate::define('export.view', [ExportPolicy::class, 'viewAny']);
        Gate::define('export.execute', [ExportPolicy::class, 'execute']);
        Gate::define('export.editMappings', [ExportPolicy::class, 'editMappings']);
    }
}
