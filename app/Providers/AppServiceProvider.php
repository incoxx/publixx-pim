<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AccessLink;
use App\Models\Attribute;
use App\Models\AttributeMapping;
use App\Models\AttributeType;
use App\Models\AttributeView;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\ImportJob;
use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\PriceRegion;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ComparisonOperator;
use App\Models\ComparisonOperatorGroup;
use App\Models\User;
use App\Models\ExportProfile;
use App\Models\ImportProfile;
use App\Models\SearchProfile;
use App\Models\ColumnProfile;
use App\Models\DictionaryEntry;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use App\Models\Project;
use App\Models\Team;
use App\Models\Workflow;
use App\Models\WorkflowStatus;
use App\Policies\AccessLinkPolicy;
use App\Policies\AttributeMappingPolicy;
use App\Policies\AttributePolicy;
use App\Policies\AttributeTypePolicy;
use App\Policies\AttributeViewPolicy;
use App\Policies\DictionaryEntryPolicy;
use App\Policies\ExportPolicy;
use App\Policies\ExportProfilePolicy;
use App\Policies\HierarchyNodePolicy;
use App\Policies\HierarchyPolicy;
use App\Policies\ImportJobPolicy;
use App\Policies\ImportProfilePolicy;
use App\Policies\MediaPolicy;
use App\Policies\MediaUsageTypePolicy;
use App\Policies\NodeAttributeAssignmentPolicy;
use App\Policies\PriceRegionPolicy;
use App\Policies\PriceTypePolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductTypePolicy;
use App\Policies\RelationTypePolicy;
use App\Policies\RolePolicy;
use App\Policies\SearchProfilePolicy;
use App\Policies\ColumnProfilePolicy;
use App\Policies\UnitGroupPolicy;
use App\Policies\UnitPolicy;
use App\Policies\ComparisonOperatorGroupPolicy;
use App\Policies\ComparisonOperatorPolicy;
use App\Policies\UserPolicy;
use App\Policies\ValueListEntryPolicy;
use App\Policies\ValueListPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TeamPolicy;
use App\Policies\WorkflowPolicy;
use App\Policies\WebsiteProfilePolicy;
use App\Policies\WorkflowStatusPolicy;
use App\Policies\WorkflowTaskPolicy;
use App\Models\WebsiteProfile;
use App\Models\WorkflowTask;
use App\Models\ConnectorConnection;
use App\Policies\ConnectorConnectionPolicy;
use App\Models\CanvaExportProfile;
use App\Policies\CanvaExportProfilePolicy;
use App\Observers\MediaObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\AzureExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\LicenseService::class);
        $this->app->singleton(\App\Services\TypesenseService::class);
    }

    public function boot(): void
    {
        // ─── Observers ─────────────────────────────────────────────────
        Media::observe(MediaObserver::class);

        // ─── SSO: Register Azure AD Socialite Driver ─────────────────
        Event::listen(SocialiteWasCalled::class, AzureExtendSocialite::class.'@handle');

        // ─── Policy Registration ─────────────────────────────────────
        Gate::policy(AccessLink::class, AccessLinkPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(AttributeType::class, AttributeTypePolicy::class);
        Gate::policy(AttributeView::class, AttributeViewPolicy::class);
        Gate::policy(Hierarchy::class, HierarchyPolicy::class);
        Gate::policy(HierarchyNode::class, HierarchyNodePolicy::class);
        Gate::policy(HierarchyNodeAttributeAssignment::class, NodeAttributeAssignmentPolicy::class);
        Gate::policy(ImportJob::class, ImportJobPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(MediaUsageType::class, MediaUsageTypePolicy::class);
        Gate::policy(PriceRegion::class, PriceRegionPolicy::class);
        Gate::policy(PriceType::class, PriceTypePolicy::class);
        Gate::policy(ProductRelationType::class, RelationTypePolicy::class);
        Gate::policy(ProductType::class, ProductTypePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(UnitGroup::class, UnitGroupPolicy::class);
        Gate::policy(ComparisonOperatorGroup::class, ComparisonOperatorGroupPolicy::class);
        Gate::policy(ComparisonOperator::class, ComparisonOperatorPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(DictionaryEntry::class, DictionaryEntryPolicy::class);
        Gate::policy(ValueList::class, ValueListPolicy::class);
        Gate::policy(ValueListEntry::class, ValueListEntryPolicy::class);
        Gate::policy(SearchProfile::class, SearchProfilePolicy::class);
        Gate::policy(ColumnProfile::class, ColumnProfilePolicy::class);
        Gate::policy(ExportProfile::class, ExportProfilePolicy::class);
        Gate::policy(ImportProfile::class, ImportProfilePolicy::class);
        Gate::policy(WebsiteProfile::class, WebsiteProfilePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(Workflow::class, WorkflowPolicy::class);
        Gate::policy(WorkflowStatus::class, WorkflowStatusPolicy::class);
        Gate::policy(WorkflowTask::class, WorkflowTaskPolicy::class);
        Gate::policy(ConnectorConnection::class, ConnectorConnectionPolicy::class);
        Gate::policy(CanvaExportProfile::class, CanvaExportProfilePolicy::class);
        Gate::policy(AttributeMapping::class, AttributeMappingPolicy::class);
        Gate::policy(\App\Models\ProductReferenceProfile::class, \App\Policies\ProductReferenceProfilePolicy::class);

        // ─── Strukturierter Content (CMS-Modul) ──────────────────────
        Gate::policy(\App\Models\ContentPage::class, \App\Policies\ContentPagePolicy::class);
        Gate::policy(\App\Models\ContentType::class, \App\Policies\ContentTypePolicy::class);
        Gate::policy(\App\Models\SectionType::class, \App\Policies\SectionTypePolicy::class);
        Gate::policy(\App\Models\Navigation::class, \App\Policies\NavigationPolicy::class);

        // ─── Conformance Gates (kein eigenes Model) ──────────────────
        // KI-Erklärung ist gesondert (Kosten!) von Ansicht/Prüfung getrennt.
        $conformanceAbility = function (User $user, string $permission): bool {
            if ($user->hasRole('Admin') || $user->hasRole('Sysadmin')) {
                return true;
            }
            return $user->hasPermissionTo($permission);
        };
        Gate::define('conformance.view', fn (User $user) => $conformanceAbility($user, 'conformance.view'));
        Gate::define('conformance.run', fn (User $user) => $conformanceAbility($user, 'conformance.run'));
        Gate::define('conformance.ai-explain', fn (User $user) => $conformanceAbility($user, 'conformance.ai-explain'));

        // ExportPolicy — no model, registered as Gates
        Gate::define('export.view', [ExportPolicy::class, 'viewAny']);
        Gate::define('export.execute', [ExportPolicy::class, 'execute']);
        Gate::define('export.editMappings', [ExportPolicy::class, 'editMappings']);
    }
}
