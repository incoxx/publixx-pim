<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AccessLink;
use App\Models\Attribute;
use App\Models\AttributeFormattingRule;
use App\Models\AttributeMetadataDefinition;
use App\Models\AttributeMapping;
use App\Models\AttributeType;
use App\Models\AttributeView;
use App\Models\ColumnProfile;
use App\Models\ComparisonOperator;
use App\Models\ComparisonOperatorGroup;
use App\Models\ConnectorConnection;
use App\Models\DictionaryEntry;
use App\Models\ExportProfile;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\ImportJob;
use App\Models\ImportProfile;
use App\Models\Media;
use App\Models\MediaCountry;
use App\Models\Language;
use App\Models\MediaLanguage;
use App\Models\MediaMotif;
use App\Models\MediaRenditionPreset;
use App\Models\MediaUsageType;
use App\Models\PriceRegion;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Project;
use App\Models\Role;
use App\Models\SearchProfile;
use App\Models\Team;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use App\Models\WebsiteProfile;
use App\Models\Workflow;
use App\Models\WorkflowStatus;
use App\Models\WorkflowTask;
use App\Observers\MediaObserver;
use App\Policies\AccessLinkPolicy;
use App\Policies\AttributeFormattingRulePolicy;
use App\Policies\AttributeMetadataDefinitionPolicy;
use App\Policies\AttributeMappingPolicy;
use App\Policies\AttributePolicy;
use App\Policies\AttributeTypePolicy;
use App\Policies\AttributeViewPolicy;
use App\Policies\ColumnProfilePolicy;
use App\Policies\ComparisonOperatorGroupPolicy;
use App\Policies\ComparisonOperatorPolicy;
use App\Policies\ConnectorConnectionPolicy;
use App\Policies\DictionaryEntryPolicy;
use App\Policies\ExportPolicy;
use App\Policies\ExportProfilePolicy;
use App\Policies\HierarchyNodePolicy;
use App\Policies\HierarchyPolicy;
use App\Policies\ImportJobPolicy;
use App\Policies\ImportProfilePolicy;
use App\Policies\MediaCountryPolicy;
use App\Policies\LanguagePolicy;
use App\Policies\MediaLanguagePolicy;
use App\Policies\MediaMotifPolicy;
use App\Policies\MediaPolicy;
use App\Policies\MediaRenditionPresetPolicy;
use App\Policies\MediaUsageTypePolicy;
use App\Policies\NodeAttributeAssignmentPolicy;
use App\Policies\PriceRegionPolicy;
use App\Policies\PriceTypePolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductTypePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\RelationTypePolicy;
use App\Policies\RolePolicy;
use App\Policies\SearchProfilePolicy;
use App\Policies\TeamPolicy;
use App\Policies\UnitGroupPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\ValueListEntryPolicy;
use App\Policies\ValueListPolicy;
use App\Policies\WebsiteProfilePolicy;
use App\Policies\WorkflowPolicy;
use App\Policies\WorkflowStatusPolicy;
use App\Policies\WorkflowTaskPolicy;
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
        // Service + Controller müssen dieselbe Cache-Instanz nutzen (lastHit/X-Cache).
        $this->app->singleton(\App\Services\Content\ContentCache::class);
        // GeoIP-Reader nur einmal pro Request öffnen (SecurityMonitor-Fallback).
        $this->app->singleton(\App\Services\Security\GeoIpResolver::class);
    }

    public function boot(): void
    {
        // ─── Observers ─────────────────────────────────────────────────
        Media::observe(MediaObserver::class);

        // ─── Collections: Morph-Map fuer polymorphe collection_attribute_values ──
        // ─── Messenger: Morph-Map fuer polymorphe messenger_message_attachments ──
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'collection' => \App\Models\Collection::class,
            'collection_item' => \App\Models\CollectionItem::class,
            'product' => \App\Models\Product::class,
        ]);

        // ─── Katalog-Freigabelink: Passwort-Brute-Force pro Token drosseln ──
        // Zusätzlich zur globalen IP-Drosselung: begrenzt Entsperr-Versuche je Token,
        // damit ein bekannt gewordener Token nicht über viele IPs durchprobiert wird.
        \Illuminate\Support\Facades\RateLimiter::for('catalog-share', function (\Illuminate\Http\Request $request) {
            $token = (string) $request->route('token');

            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('share-token:'.$token),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by('share-ip:'.$request->ip()),
            ];
        });

        // ─── SSO: Register Azure AD Socialite Driver ─────────────────
        Event::listen(SocialiteWasCalled::class, AzureExtendSocialite::class.'@handle');

        // ─── TYPO3-Integration: im CORS-Betriebsmodus die konfigurierte
        //     Kunden-Domain zusätzlich zur FRONTEND_URL als erlaubte CORS-Origin
        //     freischalten (siehe SettingController::updateTypo3Integration) ──
        $this->applyTypo3IntegrationCors();

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
        Gate::policy(MediaMotif::class, MediaMotifPolicy::class);
        Gate::policy(MediaRenditionPreset::class, MediaRenditionPresetPolicy::class);
        Gate::policy(MediaUsageType::class, MediaUsageTypePolicy::class);
        Gate::policy(Language::class, LanguagePolicy::class);
        Gate::policy(MediaLanguage::class, MediaLanguagePolicy::class);
        Gate::policy(MediaCountry::class, MediaCountryPolicy::class);
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
        Gate::policy(AttributeFormattingRule::class, AttributeFormattingRulePolicy::class);
        Gate::policy(AttributeMetadataDefinition::class, AttributeMetadataDefinitionPolicy::class);
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
        Gate::policy(AttributeMapping::class, AttributeMappingPolicy::class);
        Gate::policy(\App\Models\ProductReferenceProfile::class, \App\Policies\ProductReferenceProfilePolicy::class);

        // ─── Collections ──────────────────────────────────────────────
        Gate::policy(\App\Models\CollectionType::class, \App\Policies\CollectionTypePolicy::class);
        Gate::policy(\App\Models\Collection::class, \App\Policies\CollectionPolicy::class);

        // ─── Strukturierter Content (CMS-Modul) ──────────────────────
        Gate::policy(\App\Models\ContentPage::class, \App\Policies\ContentPagePolicy::class);
        Gate::policy(\App\Models\ContentType::class, \App\Policies\ContentTypePolicy::class);
        Gate::policy(\App\Models\SectionType::class, \App\Policies\SectionTypePolicy::class);
        Gate::policy(\App\Models\Navigation::class, \App\Policies\NavigationPolicy::class);
        Gate::policy(\App\Models\ProductWidget::class, \App\Policies\ProductWidgetPolicy::class);

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

    /**
     * Erweitert config('cors.allowed_origins') um die im Setting "typo3_integration"
     * hinterlegte Kunden-Domain, sofern Modus "cors" gewählt ist. Läuft bei jedem
     * Boot (auch Artisan-Kommandos) — daher defensiv gegen fehlende Tabelle
     * (z.B. vor der ersten Migration) und mit kurzlebigem Cache statt DB-Query
     * pro Request.
     *
     * Reihenfolge bewusst so gewählt, dass die teure Lizenzprüfung (DB-Read +
     * Ed25519-Signaturverifikation in LicenseService::resolve(), pro Request
     * unwiederholt gecacht) erst ganz am Schluss läuft — für die große Mehrheit
     * der Requests (Feature ungenutzt oder anderer Modus) genügt der günstige
     * Payload-Cache, ohne die Lizenz überhaupt anzufassen.
     */
    private function applyTypo3IntegrationCors(): void
    {
        try {
            // In ein Array gewrappt cachen, damit ein "noch nicht konfiguriert"-Ergebnis
            // (payload === null) selbst als Treffer erkannt wird — sonst würde
            // Cache::rememberForever() bei null jedes Mal erneut die DB abfragen.
            $cached = \Illuminate\Support\Facades\Cache::rememberForever(
                'typo3_integration_setting',
                fn () => ['payload' => \App\Models\Setting::getPayload('typo3_integration')],
            );

            $payload = $cached['payload'] ?? null;
            if (($payload['mode'] ?? null) !== 'cors') {
                return;
            }

            $origin = $payload['cors_origin'] ?? null;
            if (
                ! is_string($origin)
                || $origin === ''
                || ! preg_match('/^https?:\/\/[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?(:\d{1,5})?$/', $origin)
            ) {
                return;
            }

            // Lizenzprüfung erst hier — nur noch für Instanzen, die den CORS-Modus
            // tatsächlich konfiguriert haben, nicht mehr für jeden Request app-weit.
            if (! $this->app->make(\App\Services\LicenseService::class)->isModuleActive('typo3')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $origins = config('cors.allowed_origins', []);
        if (! in_array($origin, $origins, true)) {
            $origins[] = $origin;
            config(['cors.allowed_origins' => $origins]);
        }
    }
}
