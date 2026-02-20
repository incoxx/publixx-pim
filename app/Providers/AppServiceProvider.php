<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\AttributeView;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\ImportJob;
use App\Models\Media;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use App\Policies\AttributePolicy;
use App\Policies\AttributeTypePolicy;
use App\Policies\AttributeViewPolicy;
use App\Policies\ExportPolicy;
use App\Policies\HierarchyNodePolicy;
use App\Policies\HierarchyPolicy;
use App\Policies\ImportJobPolicy;
use App\Policies\MediaPolicy;
use App\Policies\NodeAttributeAssignmentPolicy;
use App\Policies\PriceTypePolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductTypePolicy;
use App\Policies\RelationTypePolicy;
use App\Policies\RolePolicy;
use App\Policies\UnitGroupPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\ValueListEntryPolicy;
use App\Policies\ValueListPolicy;
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
        // ─── Policy Registration ─────────────────────────────────────
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(AttributeType::class, AttributeTypePolicy::class);
        Gate::policy(AttributeView::class, AttributeViewPolicy::class);
        Gate::policy(Hierarchy::class, HierarchyPolicy::class);
        Gate::policy(HierarchyNode::class, HierarchyNodePolicy::class);
        Gate::policy(HierarchyNodeAttributeAssignment::class, NodeAttributeAssignmentPolicy::class);
        Gate::policy(ImportJob::class, ImportJobPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(PriceType::class, PriceTypePolicy::class);
        Gate::policy(ProductRelationType::class, RelationTypePolicy::class);
        Gate::policy(ProductType::class, ProductTypePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(UnitGroup::class, UnitGroupPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ValueList::class, ValueListPolicy::class);
        Gate::policy(ValueListEntry::class, ValueListEntryPolicy::class);

        // ExportPolicy — no model, registered as Gates
        Gate::define('export.view', [ExportPolicy::class, 'viewAny']);
        Gate::define('export.execute', [ExportPolicy::class, 'execute']);
        Gate::define('export.editMappings', [ExportPolicy::class, 'editMappings']);
    }
}
