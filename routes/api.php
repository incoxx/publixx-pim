<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AssetCatalogController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TestRunnerController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Controllers\Api\V1\WorkflowStatusController;
use App\Http\Controllers\Api\V1\WorkflowTaskController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\PdfController;
use App\Http\Controllers\Api\V1\AttributeController;
use App\Http\Controllers\Api\V1\AttributeTypeController;
use App\Http\Controllers\Api\V1\AttributeViewController;
use App\Http\Controllers\Api\V1\ApiTesterController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SsoController;
use App\Http\Controllers\Api\V1\BulkEditorController;
use App\Http\Controllers\Api\V1\BulkUpdateController;
use App\Http\Controllers\Api\V1\DatabaseConsistencyController;
use App\Http\Controllers\Api\V1\DatabaseViewerController;
use App\Http\Controllers\Api\V1\DebugController;
use App\Http\Controllers\Api\V1\DictionaryEntryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\DeploymentController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\ExportFileController;
use App\Http\Controllers\Api\V1\ExportJobController;
use App\Http\Controllers\Api\V1\ExportProfileController;
use App\Http\Controllers\Api\V1\BmecatImportController;
use App\Http\Controllers\Api\V1\BmecatExportController;
use App\Http\Controllers\Api\V1\JsonExportImportController;
use App\Http\Controllers\Api\V1\ImportProfileController;
use App\Http\Controllers\Api\V1\LoadDemoDataController;
use App\Http\Controllers\Api\V1\OfflineCatalogController;
use App\Http\Controllers\Api\V1\TestDataGeneratorController;
use App\Http\Controllers\Api\V1\ScheduledActionController;
use App\Http\Controllers\Api\V1\SearchProfileController;
use App\Http\Controllers\Api\V1\ColumnProfileController;
use App\Http\Controllers\Api\V1\WebsiteProfileController;
use App\Http\Controllers\Api\V1\HierarchyAttributeAssignmentController;
use App\Http\Controllers\Api\V1\HierarchyController;
use App\Http\Controllers\Api\V1\HierarchyNodeMediaController;
use App\Http\Controllers\Api\V1\OutputHierarchyProductAssignmentController;
use App\Http\Controllers\Api\V1\HierarchyNodeAttributeValueController;
use App\Http\Controllers\Api\V1\HierarchyNodeController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\MediaAttributeValueController;
use App\Http\Controllers\Api\V1\ManufacturerController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MediaUsageTypeController;
use App\Http\Controllers\Api\V1\NodeAttributeAssignmentController;
use App\Http\Controllers\Api\V1\PqlController;
use App\Http\Controllers\Api\V1\PriceRegionController;
use App\Http\Controllers\Api\V1\PriceTypeController;
use App\Http\Controllers\Api\V1\ProductTypeController;
use App\Http\Controllers\Api\V1\ProductAttributeValueController;
use App\Http\Controllers\Api\V1\TmsProxyController;
use App\Http\Controllers\Api\V1\TranslationJobController;
use App\Http\Controllers\Api\V1\TranslationXliffController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductMediaController;
use App\Http\Controllers\Api\V1\ProductExportController;
use App\Http\Controllers\Api\V1\ProductSearchController;
use App\Http\Controllers\Api\V1\WatchlistController;
use App\Http\Controllers\Api\V1\ProductPriceController;
use App\Http\Controllers\Api\V1\ProductRelationAttributeValueController;
use App\Http\Controllers\Api\V1\ProductRelationController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\RoleRestrictionController;
use App\Http\Controllers\Api\V1\ProductVersionController;
use App\Http\Controllers\Api\V1\PublixxDatasetController;
use App\Http\Controllers\Api\V1\PdfTemplateController;
use App\Http\Controllers\Api\V1\ApiStreamController;
use App\Http\Controllers\Api\V1\ApiTemplateController;
use App\Http\Controllers\Api\V1\CatalogTemplateController;
use App\Http\Controllers\Api\V1\ReportTemplateController;
use App\Http\Controllers\Api\V1\RelationTypeController;
use App\Http\Controllers\Api\V1\ResetDataController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SystemInfoController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\LicenseGeneratorController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitGroupController;
use App\Http\Controllers\Api\V1\ComparisonOperatorGroupController;
use App\Http\Controllers\Api\V1\ComparisonOperatorController;
use App\Http\Controllers\Api\V1\AccessLinkController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\UserAuditController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ValueListController;
use App\Http\Controllers\Api\V1\ValueListEntryController;
use App\Http\Controllers\Api\V1\AttributeMappingController;
use App\Http\Controllers\Api\V1\CanvaExportProfileController;
use App\Http\Controllers\Api\V1\ConnectorController;
use App\Http\Controllers\Api\V1\QuickSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| anyPIM — Merged API Routes
|--------------------------------------------------------------------------
|
| Agents: 2 (Auth), 3 (API), 5 (PQL), 7 (Export/Publixx)
| All routes prefixed with /api/v1
|
*/

// =========================================================================
// Agent 2: Auth (public — no auth required)
// =========================================================================
Route::prefix('v1/auth')->middleware('throttle.pim:auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// =========================================================================
// SSO (public — no auth required, Enterprise: sso)
// =========================================================================
Route::prefix('v1/auth/sso')->middleware(['web', 'throttle.pim:auth', 'module:sso'])->group(function () {
    Route::get('config', [SsoController::class, 'config']);
    Route::get('redirect', [SsoController::class, 'redirect']);
    Route::get('callback', [SsoController::class, 'callback']);
});

// =========================================================================
// Public media file serving (no auth — used by <img src> tags)
// =========================================================================
Route::prefix('v1')->middleware('throttle.pim:media')->group(function () {
    Route::get('media/file/{filename}', [MediaController::class, 'serve'])->name('media.serve');
    Route::get('media/thumb/{medium}', [MediaController::class, 'thumb'])->name('media.thumb');
});

// =========================================================================
// Public Asset Catalog API (no auth required)
// =========================================================================
Route::prefix('v1/asset-catalog')->middleware(['throttle.pim', 'catalog.access'])->group(function () {
    Route::get('assets', [AssetCatalogController::class, 'assets']);
    Route::get('assets/{medium}', [AssetCatalogController::class, 'asset']);
    Route::get('assets/{medium}/products', [AssetCatalogController::class, 'assetProducts']);
    Route::get('assets/{medium}/nodes', [AssetCatalogController::class, 'assetNodes']);
    Route::get('folders', [AssetCatalogController::class, 'folders']);
    Route::post('download', [AssetCatalogController::class, 'download']);
});

// =========================================================================
// Public PDF API (no auth — page images and search for asset catalog)
// =========================================================================
Route::prefix('v1/pdf')->middleware(['throttle.pim', 'catalog.access'])->group(function () {
    Route::get('search', [PdfController::class, 'search']);
    Route::get('by-media/{mediaId}', [PdfController::class, 'byMedia']);
    Route::get('{pdfDocument}', [PdfController::class, 'show']);
    Route::get('{pdfDocument}/page/{pageNumber}', [PdfController::class, 'page'])->where('pageNumber', '[0-9]+');
    Route::get('{pdfDocument}/pages', [PdfController::class, 'pages']);
    Route::get('{pdfDocument}/status', [PdfController::class, 'status']);
});

// =========================================================================
// Public Catalog API (no auth required)
// =========================================================================
Route::prefix('v1/catalog')->middleware('throttle.pim')->group(function () {
    // Settings and media always public (frontend needs access_mode before login,
    // media is loaded via <img> tags which cannot send Bearer tokens)
    Route::get('settings', [SettingController::class, 'catalogTheme']);
    Route::get('media/{filename}', [CatalogController::class, 'media'])->name('catalog.media');

    // Data routes protected by catalog access control
    Route::middleware('catalog.access')->group(function () {
        Route::get('products', [CatalogController::class, 'products']);
        Route::get('products/export.json', [CatalogController::class, 'productsExportJson']);
        Route::get('products/{product}', [CatalogController::class, 'product']);
        Route::get('products/{product}/json', [CatalogController::class, 'productJson']);
        Route::get('categories', [CatalogController::class, 'categories']);
        Route::get('categories/{nodeId}/assets', [CatalogController::class, 'categoryAssets']);
        Route::get('facets', [CatalogController::class, 'facets']);
        Route::get('attribute-groups', [CatalogController::class, 'attributeGroups']);

        // PDF, Excel & Compare
        Route::get('products/{product}/pdf', [CatalogController::class, 'productPdf']);
        Route::post('wishlist/pdf', [CatalogController::class, 'wishlistPdf']);
        Route::post('wishlist/excel', [CatalogController::class, 'wishlistExcel']);
        Route::post('products/compare', [CatalogController::class, 'compareProducts']);
    });
});

// =========================================================================
// API Designer Stream Endpoints (own auth via API key / bearer)
// =========================================================================
Route::prefix('v1')->middleware('throttle.pim')->group(function () {
    Route::get('api-streams/{slug}', [ApiStreamController::class, 'stream']);
    Route::post('api-streams/{slug}', [ApiStreamController::class, 'import']);
});

// =========================================================================
// Health: Public healthcheck (no auth)
// =========================================================================
Route::get('v1/health', HealthController::class);

// =========================================================================
// Debug: Log access (no auth — test server only)
// =========================================================================
Route::prefix('v1/debug')->middleware('throttle.pim')->group(function () {
    Route::get('logs', [DebugController::class, 'logs']);
    Route::get('logs/parsed', [DebugController::class, 'parsedLogs']);
    Route::get('logs/clear', [DebugController::class, 'clearLogs']);
    Route::delete('logs', [DebugController::class, 'clearLogs']);
});

// =========================================================================
// Access Link Redeem (public — no auth required)
// =========================================================================
Route::post('v1/access-links/redeem/{token}', [AccessLinkController::class, 'redeem'])
    ->middleware('throttle.pim');

// =========================================================================
// Agent 2: Auth (authenticated)
// =========================================================================
Route::prefix('v1/auth')->middleware(['auth:sanctum', 'throttle.pim'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);
});

// =========================================================================
// Offline Catalog: Routen mit Token-Auth via Query-Parameter
// (Browser-Navigation kann keinen Authorization-Header senden)
// =========================================================================
Route::get('v1/admin/offline-catalog/download', [OfflineCatalogController::class, 'download'])
    ->middleware('throttle.pim');
Route::get('v1/admin/offline-catalog/preview', [OfflineCatalogController::class, 'preview'])
    ->middleware('throttle.pim');
// Kein Rate-Limiting für Preview-Assets — der Offline-Katalog lädt hunderte
// JSON-Chunks parallel (215 Chunks bei 107k Produkten)
Route::get('v1/admin/offline-catalog/preview-asset/{path}', [OfflineCatalogController::class, 'previewAsset'])
    ->where('path', '.*');

// =========================================================================
// All authenticated routes
// =========================================================================
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle.pim'])->group(function () {

    // =====================================================================
    // Access Links (Zugangslink-Generator)
    // =====================================================================
    Route::get('access-links/report', [AccessLinkController::class, 'report']);
    Route::apiResource('access-links', AccessLinkController::class)->except(['show', 'update']);

    // =====================================================================
    // Agent 2: User & Role Management
    // =====================================================================
    Route::apiResource('users', UserController::class);
    Route::get('users/{user}/dependencies', [UserController::class, 'dependencies']);
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::put('roles/bulk-permissions', [RoleController::class, 'bulkSyncPermissions']);
    Route::apiResource('roles', RoleController::class);
    Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
    Route::get('roles/{role}/restrictions', [RoleRestrictionController::class, 'index']);
    Route::get('roles/{role}/restrictions/{type}', [RoleRestrictionController::class, 'show']);
    Route::put('roles/{role}/restrictions/{type}', [RoleRestrictionController::class, 'sync']);
    Route::delete('roles/{role}/restrictions/{type}', [RoleRestrictionController::class, 'destroy']);
    Route::get('roles/{role}/tab-permissions', [RoleRestrictionController::class, 'tabPermissions']);
    Route::put('roles/{role}/tab-permissions', [RoleRestrictionController::class, 'syncTabPermissions']);

    // =====================================================================
    // License Management
    // =====================================================================
    Route::get('license', [LicenseController::class, 'show']);
    Route::put('license', [LicenseController::class, 'update']);

    // =====================================================================
    // License Generator (hidden admin tool)
    // =====================================================================
    Route::post('license-generator/validate-key', [LicenseGeneratorController::class, 'validateKey']);
    Route::post('license-generator/generate', [LicenseGeneratorController::class, 'generate']);
    Route::post('license-generator/generate-keypair', [LicenseGeneratorController::class, 'generateKeypair']);

    // =====================================================================
    // Audit Log (Änderungsprotokoll)
    // =====================================================================
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/export', [AuditLogController::class, 'export']);
    Route::delete('audit-logs', [AuditLogController::class, 'destroy']);

    // User Audit Trail
    Route::get('user-audit-logs', [UserAuditController::class, 'index']);
    Route::get('user-audit-logs/export', [UserAuditController::class, 'export']);
    Route::delete('user-audit-logs', [UserAuditController::class, 'destroy']);

    // =====================================================================
    // Agent 3: Attributes
    // =====================================================================
    Route::put('attributes/bulk-update', [AttributeController::class, 'bulkUpdate']);
    Route::post('attributes/all-ids', [AttributeController::class, 'allIds']);
    Route::post('attributes/bulk-delete', [AttributeController::class, 'bulkDelete']);
    Route::apiResource('attributes', AttributeController::class);
    Route::get('attributes/{attribute}/dependencies', [AttributeController::class, 'dependencies']);
    Route::post('attributes/{attribute}/copy', [AttributeController::class, 'copy']);
    Route::post('attributes/{attribute}/migrate-language', [AttributeController::class, 'migrateLanguage']);

    // =====================================================================
    // Agent 3: Attribute Types
    // =====================================================================
    Route::apiResource('attribute-types', AttributeTypeController::class);
    Route::get('attribute-types/{attribute_type}/dependencies', [AttributeTypeController::class, 'dependencies']);

    // =====================================================================
    // Manufacturers (Hersteller)
    // =====================================================================
    Route::apiResource('manufacturers', ManufacturerController::class);
    Route::get('manufacturers/{manufacturer}/dependencies', [ManufacturerController::class, 'dependencies']);

    // =====================================================================
    // Agent 3: Unit Groups & Units
    // =====================================================================
    Route::apiResource('unit-groups', UnitGroupController::class);
    Route::get('unit-groups/{unit_group}/dependencies', [UnitGroupController::class, 'dependencies']);
    Route::apiResource('unit-groups.units', UnitController::class)->shallow();
    Route::get('units/{unit}/dependencies', [UnitController::class, 'dependencies']);

    // Comparison Operator Groups & Operators
    Route::apiResource('comparison-operator-groups', ComparisonOperatorGroupController::class);
    Route::get('comparison-operator-groups/{comparison_operator_group}/dependencies', [ComparisonOperatorGroupController::class, 'dependencies']);
    Route::apiResource('comparison-operator-groups.comparison-operators', ComparisonOperatorController::class)->shallow();
    Route::get('comparison-operators/{comparison_operator}/dependencies', [ComparisonOperatorController::class, 'dependencies']);

    // =====================================================================
    // Agent 3: Value Lists & Entries
    // =====================================================================
    Route::apiResource('value-lists', ValueListController::class);
    Route::get('value-lists/{value_list}/dependencies', [ValueListController::class, 'dependencies']);
    Route::apiResource('value-lists.entries', ValueListEntryController::class)->shallow();
    Route::get('entries/{entry}/dependencies', [ValueListEntryController::class, 'dependencies']);

    // =====================================================================
    // Dictionary Entries
    // =====================================================================
    Route::apiResource('dictionary-entries', DictionaryEntryController::class);
    Route::get('dictionary-entries/{dictionary_entry}/dependencies', [DictionaryEntryController::class, 'dependencies']);

    // =====================================================================
    // Agent 3: Attribute Views & Assignments
    // =====================================================================
    Route::apiResource('attribute-views', AttributeViewController::class);
    Route::get('attribute-views/{attribute_view}/dependencies', [AttributeViewController::class, 'dependencies']);
    Route::post('attribute-views/{attribute_view}/attributes', [AttributeViewController::class, 'assignAttribute']);
    Route::delete('attribute-views/{attribute_view}/attributes/{attribute}', [AttributeViewController::class, 'removeAttribute']);

    // =====================================================================
    // Agent 3: Product Types
    // =====================================================================
    Route::apiResource('product-types', ProductTypeController::class);
    Route::get('product-types/{product_type}/dependencies', [ProductTypeController::class, 'dependencies']);
    Route::get('product-types/{product_type}/schema', [ProductTypeController::class, 'schema']);

    // =====================================================================
    // Agent 3: Hierarchies
    // =====================================================================
    Route::apiResource('hierarchies', HierarchyController::class);
    Route::get('hierarchies/{hierarchy}/dependencies', [HierarchyController::class, 'dependencies']);
    Route::get('hierarchies/{hierarchy}/tree', [HierarchyController::class, 'tree']);

    // Hierarchy-level attribute assignments
    Route::get('hierarchies/{hierarchy}/attributes', [HierarchyAttributeAssignmentController::class, 'index']);
    Route::get('hierarchies/{hierarchy}/all-node-attributes', [HierarchyAttributeAssignmentController::class, 'allNodeAttributes']);
    Route::post('hierarchies/{hierarchy}/attributes', [HierarchyAttributeAssignmentController::class, 'store']);
    Route::patch('hierarchy-attribute-assignments/{hierarchy_attribute_assignment}', [HierarchyAttributeAssignmentController::class, 'update']);
    Route::delete('hierarchy-attribute-assignments/{hierarchy_attribute_assignment}', [HierarchyAttributeAssignmentController::class, 'destroy']);

    Route::apiResource('hierarchies.nodes', HierarchyNodeController::class)
        ->shallow()
        ->parameters(['nodes' => 'hierarchy_node']);
    Route::get('hierarchy-nodes/{hierarchy_node}/dependencies', [HierarchyNodeController::class, 'dependencies']);
    Route::delete('hierarchy-nodes/{hierarchy_node}', [HierarchyNodeController::class, 'destroy']);
    Route::get('hierarchy-nodes', [HierarchyNodeController::class, 'searchAll']);
    Route::put('hierarchy-nodes/{hierarchy_node}/move', [HierarchyNodeController::class, 'move']);
    Route::post('hierarchy-nodes/{hierarchy_node}/duplicate', [HierarchyNodeController::class, 'duplicate']);

    // =====================================================================
    // Agent 3: Hierarchy Node — Attribute Assignments
    // =====================================================================
    Route::get('hierarchy-nodes/{hierarchy_node}/attributes', [NodeAttributeAssignmentController::class, 'index']);
    Route::post('hierarchy-nodes/{hierarchy_node}/attributes', [NodeAttributeAssignmentController::class, 'store']);
    Route::put('node-attribute-assignments/bulk-sort', [NodeAttributeAssignmentController::class, 'bulkSort']);
    Route::put('node-attribute-assignments/{node_attribute_assignment}', [NodeAttributeAssignmentController::class, 'update']);
    Route::delete('node-attribute-assignments/{node_attribute_assignment}', [NodeAttributeAssignmentController::class, 'destroy']);

    // =====================================================================
    // Hierarchy Node — Attribute Values (EAV on nodes)
    // =====================================================================
    Route::get('hierarchy-nodes/{hierarchy_node}/attribute-values', [HierarchyNodeAttributeValueController::class, 'index']);
    Route::put('hierarchy-nodes/{hierarchy_node}/attribute-values', [HierarchyNodeAttributeValueController::class, 'bulkUpdate']);
    Route::delete('hierarchy-node-attribute-values/{hierarchy_node_attribute_value}', [HierarchyNodeAttributeValueController::class, 'destroy']);

    // =====================================================================
    // Hierarchy Node — Media Assignments
    // =====================================================================
    Route::get('hierarchy-nodes/{hierarchy_node}/media', [HierarchyNodeMediaController::class, 'index']);
    Route::post('hierarchy-nodes/{hierarchy_node}/media', [HierarchyNodeMediaController::class, 'store']);
    Route::delete('hierarchy-node-media/{hierarchy_node_medium}', [HierarchyNodeMediaController::class, 'destroy']);

    // =====================================================================
    // Output Hierarchy Product Assignments
    // =====================================================================
    Route::get('hierarchy-nodes/{hierarchy_node}/output-products', [OutputHierarchyProductAssignmentController::class, 'index']);
    Route::post('hierarchy-nodes/{hierarchy_node}/output-products', [OutputHierarchyProductAssignmentController::class, 'store']);
    Route::put('hierarchy-nodes/{hierarchy_node}/output-products/sort', [OutputHierarchyProductAssignmentController::class, 'bulkSort']);
    Route::post('hierarchy-nodes/{hierarchy_node}/output-products/bulk-assign', [OutputHierarchyProductAssignmentController::class, 'bulkAssign']);
    Route::post('hierarchy-nodes/{hierarchy_node}/master-products/bulk-assign', [OutputHierarchyProductAssignmentController::class, 'bulkAssignMaster']);
    Route::delete('output-hierarchy-product-assignments/{assignment}', [OutputHierarchyProductAssignmentController::class, 'destroy']);
    Route::get('output-hierarchy-product-assignments/{assignment}/relationship-attributes', [OutputHierarchyProductAssignmentController::class, 'relationshipAttributes']);
    Route::put('output-hierarchy-product-assignments/{assignment}/relationship-attributes', [OutputHierarchyProductAssignmentController::class, 'updateRelationshipAttributes']);
    Route::get('products/{product}/output-hierarchy-assignments', [OutputHierarchyProductAssignmentController::class, 'productAssignments']);

    // Master Hierarchy Product Assignments
    Route::post('hierarchy-nodes/{hierarchy_node}/master-products', [OutputHierarchyProductAssignmentController::class, 'assignMasterProduct']);
    Route::delete('hierarchy-nodes/{hierarchy_node}/master-products/{product}', [OutputHierarchyProductAssignmentController::class, 'removeMasterProduct']);

    // =====================================================================
    // Agent 3: Products
    // =====================================================================
    // Product Compare (must be before apiResource to avoid {product} conflict)
    Route::get('products/compare', [ProductController::class, 'compare']);

    // Product Search (SQL-based, replaces PQL)
    Route::post('products/search', [ProductSearchController::class, 'search']);
    Route::post('products/search/ids', [ProductSearchController::class, 'allIds']);
    Route::post('products/search/count', [ProductSearchController::class, 'count']);
    Route::get('products/search/attributes', [ProductSearchController::class, 'searchableAttributes']);
    Route::post('products/bulk-delete', [ProductSearchController::class, 'bulkDelete']);

    // Schnellsuche (Google-artige Suche über alle Entitäten)
    Route::get('quick-search', [QuickSearchController::class, 'search']);

    // Product Excel Export (configurable columns + filters)
    Route::post('products/export/excel', [ProductExportController::class, 'exportExcel']);

    // Bulk Editor
    Route::post('products/bulk-edit', [BulkEditorController::class, 'load']);
    Route::put('products/bulk-edit', [BulkEditorController::class, 'save']);

    // Bulk Update (Massendatenpflege)
    Route::post('products/common-attributes', [BulkUpdateController::class, 'commonAttributes']);
    Route::post('products/bulk-update/preview', [BulkUpdateController::class, 'preview']);
    Route::put('products/bulk-update', [BulkUpdateController::class, 'execute']);

    // Product Duplicate
    Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate']);

    Route::apiResource('products', ProductController::class);
    Route::get('products/{product}/dependencies', [ProductController::class, 'dependencies']);
    Route::get('products/{product}/available-transitions', [ProductController::class, 'availableTransitions']);
    Route::get('products/{product}/workflow-history', [ProductController::class, 'workflowHistory']);

    // Product Preview
    Route::get('products/{product}/preview', [ProductController::class, 'preview']);
    Route::get('products/{product}/preview/export.xlsx', [ProductController::class, 'previewExportExcel']);
    Route::get('products/{product}/preview/export.pdf', [ProductController::class, 'previewExportPdf']);
    Route::get('products/{product}/completeness', [ProductController::class, 'completeness']);

    // Product Attribute Values
    Route::get('products/{product}/attribute-values', [ProductAttributeValueController::class, 'index']);
    Route::get('products/{product}/resolved-attributes', [ProductAttributeValueController::class, 'resolved']);
    Route::put('products/{product}/attribute-values', [ProductAttributeValueController::class, 'bulkUpdate']);

    // Output Hierarchy (Channel) Attribute Values
    Route::get('products/{product}/output-hierarchy-resolved-attributes', [ProductAttributeValueController::class, 'resolvedOutputHierarchy']);
    Route::put('products/{product}/output-hierarchy-attribute-values', [ProductAttributeValueController::class, 'bulkUpdateOutputHierarchy']);

    // Translation XLIFF Export/Import
    Route::get('translations/xliff/export', [TranslationXliffController::class, 'export']);
    Route::post('translations/xliff/import', [TranslationXliffController::class, 'import']);

    // Product Variants
    Route::get('products/{product}/variants', [ProductVariantController::class, 'index']);
    Route::post('products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::post('products/{product}/variants/generate', [ProductVariantController::class, 'generate']);
    Route::get('products/{product}/variant-rules', [ProductVariantController::class, 'rules']);
    Route::put('products/{product}/variant-rules', [ProductVariantController::class, 'updateRules']);

    // Product Versions
    Route::get('products/{product}/versions/compare', [ProductVersionController::class, 'compare']);
    Route::get('products/{product}/versions', [ProductVersionController::class, 'index']);
    Route::post('products/{product}/versions', [ProductVersionController::class, 'store']);
    Route::get('products/{product}/versions/{version}', [ProductVersionController::class, 'show']);
    Route::post('products/{product}/versions/{version}/activate', [ProductVersionController::class, 'activate']);
    Route::post('products/{product}/versions/{version}/schedule', [ProductVersionController::class, 'schedule']);
    Route::post('products/{product}/versions/{version}/cancel-schedule', [ProductVersionController::class, 'cancelSchedule']);
    Route::post('products/{product}/versions/{version}/revert', [ProductVersionController::class, 'revert']);

    // =====================================================================
    // PDF Documents (Admin: reprocess)
    // =====================================================================
    Route::post('pdf/{pdfDocument}/reprocess', [PdfController::class, 'reprocess']);

    // =====================================================================
    // Agent 3: Media
    // =====================================================================
    Route::get('media/diagnostics', [MediaController::class, 'diagnostics']);
    Route::get('media/processing-status', [MediaController::class, 'processingStatus']);
    Route::post('media/bulk-move', [MediaController::class, 'bulkMove']);
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDelete']);
    Route::get('media/revision/{revision}/download', [MediaController::class, 'downloadRevision']);
    Route::post('media/import-url', [MediaController::class, 'importFromUrl']);
    Route::post('media/bulk-import-urls', [MediaController::class, 'bulkImportFromUrls']);
    Route::post('media/auto-match', [MediaController::class, 'autoMatch']);
    Route::apiResource('media', MediaController::class);
    Route::get('media/{medium}/dependencies', [MediaController::class, 'dependencies']);
    Route::get('media/{medium}/usage', [MediaController::class, 'usage']);
    Route::get('media/{medium}/revisions', [MediaController::class, 'revisions']);
    Route::get('media/{medium}/relink-preview', [MediaController::class, 'relinkPreview']);
    Route::post('media/relink', [MediaController::class, 'relink']);
    // media/file/{filename} and media/thumb/{medium} are registered outside auth group (public access)
    Route::get('media/{medium}/attribute-values', [MediaAttributeValueController::class, 'index']);
    Route::put('media/{medium}/attribute-values', [MediaAttributeValueController::class, 'bulkUpdate']);

    Route::apiResource('media-usage-types', MediaUsageTypeController::class);
    Route::get('media-usage-types/{media_usage_type}/dependencies', [MediaUsageTypeController::class, 'dependencies']);

    Route::get('products/{product}/media', [ProductMediaController::class, 'index']);
    Route::post('products/{product}/media', [ProductMediaController::class, 'store']);
    Route::delete('product-media/{product_medium}', [ProductMediaController::class, 'destroy']);

    // =====================================================================
    // Agent 3: Prices
    // =====================================================================
    Route::apiResource('price-types', PriceTypeController::class);
    Route::get('price-types/{price_type}/dependencies', [PriceTypeController::class, 'dependencies']);
    Route::apiResource('price-regions', PriceRegionController::class);
    Route::get('price-regions/{price_region}/dependencies', [PriceRegionController::class, 'dependencies']);
    Route::get('products/{product}/prices', [ProductPriceController::class, 'index']);
    Route::post('products/{product}/prices', [ProductPriceController::class, 'store']);
    Route::put('product-prices/{product_price}', [ProductPriceController::class, 'update']);
    Route::delete('product-prices/{product_price}', [ProductPriceController::class, 'destroy']);

    // =====================================================================
    // Agent 3: Relations
    // =====================================================================
    Route::apiResource('relation-types', RelationTypeController::class);
    Route::get('relation-types/{relation_type}/dependencies', [RelationTypeController::class, 'dependencies']);
    Route::put('relation-types/{relationType}/default-attributes', [RelationTypeController::class, 'updateDefaultAttributes']);
    Route::get('products/{product}/relations', [ProductRelationController::class, 'index']);
    Route::post('products/{product}/relations', [ProductRelationController::class, 'store']);
    Route::delete('product-relations/{product_relation}', [ProductRelationController::class, 'destroy']);

    // Product Relation — Attribute Values (EAV on relation edges)
    Route::get('product-relations/{product_relation}/attribute-values', [ProductRelationAttributeValueController::class, 'index']);
    Route::put('product-relations/{product_relation}/attribute-values', [ProductRelationAttributeValueController::class, 'bulkUpdate']);
    Route::delete('product-relation-attribute-values/{product_relation_attribute_value}', [ProductRelationAttributeValueController::class, 'destroy']);

    // =====================================================================
    // Agent 3 + 6: Import
    // =====================================================================
    Route::get('imports/templates/{type}', [ImportController::class, 'template']);
    Route::get('imports/export-format', [ImportController::class, 'exportImportFormat']);
    Route::post('imports', [ImportController::class, 'store']);
    Route::get('imports/{import}', [ImportController::class, 'show']);
    Route::get('imports/{import}/preview', [ImportController::class, 'preview']);
    Route::post('imports/{import}/execute', [ImportController::class, 'execute']);
    Route::get('imports/{import}/result', [ImportController::class, 'result']);
    Route::get('imports/{import}/logs', [ImportController::class, 'logs']);
    Route::get('imports/{import}/errors/download', [ImportController::class, 'downloadErrors']);
    Route::delete('imports/{import}', [ImportController::class, 'destroy']);

    // =====================================================================
    // Search Profiles (Suchprofile)
    // =====================================================================
    Route::apiResource('search-profiles', SearchProfileController::class)->except(['show']);
    Route::get('search-profiles/{search_profile}/dependencies', [SearchProfileController::class, 'dependencies']);

    // =====================================================================
    // Column Profiles (Spaltenprofile)
    // =====================================================================
    Route::apiResource('column-profiles', ColumnProfileController::class)->except(['show']);

    // =====================================================================
    // Export Profiles (Exportprofile)
    // =====================================================================
    Route::apiResource('export-profiles', ExportProfileController::class)->except(['show']);
    Route::post('export-profiles/{export_profile}/execute', [ExportProfileController::class, 'execute']);
    Route::get('export-profiles/{export_profile}/stream', [ExportProfileController::class, 'stream']);

    // =====================================================================
    // Import Profiles (Importprofile)
    // =====================================================================
    Route::apiResource('import-profiles', ImportProfileController::class)->except(['show']);
    Route::post('import-profiles/analyze', [ImportProfileController::class, 'analyze']);

    // =====================================================================
    // Website Profiles (Website-Profile)
    // =====================================================================
    Route::apiResource('website-profiles', WebsiteProfileController::class)->except(['show']);
    Route::post('website-profiles/{website_profile}/activate', [WebsiteProfileController::class, 'activate']);
    Route::post('import-profiles/auto-generate-attributes', [ImportProfileController::class, 'autoGenerateAttributes']);
    Route::post('import-profiles/{import_profile}/preview', [ImportProfileController::class, 'preview']);

    // =====================================================================
    // Watchlist (Merkliste)
    // =====================================================================
    Route::prefix('watchlist')->group(function () {
        Route::get('/', [WatchlistController::class, 'index']);
        Route::post('/', [WatchlistController::class, 'store']);
        Route::post('bulk', [WatchlistController::class, 'bulkStore']);
        Route::get('product-ids', [WatchlistController::class, 'productIds']);
        Route::post('bulk-remove', [WatchlistController::class, 'bulkRemove']);
        Route::delete('all', [WatchlistController::class, 'removeAll']);
        Route::delete('{watchlistItem}', [WatchlistController::class, 'destroy']);
        Route::delete('product/{productId}', [WatchlistController::class, 'removeByProduct']);
        Route::get('export/excel', [WatchlistController::class, 'exportExcel']);
        Route::get('export/pdf', [WatchlistController::class, 'exportPdf']);
        Route::get('export/pdf-zip', [WatchlistController::class, 'exportPdfZip']);
        Route::get('export/xliff', [WatchlistController::class, 'exportXliff']);
        Route::post('export/pdf-template', [WatchlistController::class, 'exportPdfTemplate']);
    });

    // =====================================================================
    // Agent 5: PQL
    // =====================================================================
    Route::prefix('pql')->group(function () {
        Route::post('query', [PqlController::class, 'query']);
        Route::post('query/count', [PqlController::class, 'count']);
        Route::post('query/validate', [PqlController::class, 'validate']);
        Route::post('query/explain', [PqlController::class, 'explain']);
    });

    // =====================================================================
    // Agent 7: Export
    // =====================================================================
    Route::prefix('export')->group(function () {
        Route::get('products', [ExportController::class, 'index']);
        Route::get('products/{id}', [ExportController::class, 'show']);
        Route::post('products/bulk', [ExportController::class, 'bulk']);
        Route::get('products/{id}/publixx', [ExportController::class, 'publixx']);
        Route::post('query', [ExportController::class, 'query']);
    });

    // =====================================================================
    // JSON Export/Import
    // =====================================================================
    Route::prefix('json-export')->group(function () {
        Route::get('/', [JsonExportImportController::class, 'export']);
        Route::post('/', [JsonExportImportController::class, 'exportFiltered']);
        Route::get('sections', [JsonExportImportController::class, 'sections']);
    });
    Route::prefix('json-import')->group(function () {
        Route::post('/', [JsonExportImportController::class, 'import']);
        Route::post('validate', [JsonExportImportController::class, 'validate']);
    });
    // Enterprise: BMEcat Import/Export
    Route::middleware('module:bmecat')->group(function () {
        Route::prefix('bmecat-import')->group(function () {
            Route::post('/', [BmecatImportController::class, 'import']);
            Route::post('validate', [BmecatImportController::class, 'validate']);
            Route::post('cancel', [BmecatImportController::class, 'cancel']);
            Route::post('upload-init', [BmecatImportController::class, 'uploadInit']);
            Route::post('upload-chunk', [BmecatImportController::class, 'uploadChunk']);
            Route::post('upload-complete', [BmecatImportController::class, 'uploadComplete']);
        });
        Route::post('bmecat-export', [BmecatExportController::class, 'export']);
    });

    // =====================================================================
    // Attribut-Mappings (Klassifikations-Zuordnung)
    // =====================================================================
    Route::apiResource('attribute-mappings', AttributeMappingController::class);
    Route::post('attribute-mappings/bulk', [AttributeMappingController::class, 'bulkStore']);
    Route::get('attribute-mapping-rules', [AttributeMappingController::class, 'rules']);
    Route::post('attribute-mapping-rules', [AttributeMappingController::class, 'storeRule']);
    Route::put('attribute-mapping-rules/{rule}', [AttributeMappingController::class, 'updateRule']);
    Route::delete('attribute-mapping-rules/{rule}', [AttributeMappingController::class, 'destroyRule']);
    Route::post('attribute-mappings/sync/product/{product}', [AttributeMappingController::class, 'syncProduct']);
    Route::post('attribute-mappings/sync/batch', [AttributeMappingController::class, 'syncBatch']);
    Route::post('attribute-mappings/sync/bulk', [AttributeMappingController::class, 'syncBulk']);
    Route::post('attribute-mappings/export-excel', [AttributeMappingController::class, 'exportExcel']);
    Route::post('attribute-mappings/import-excel', [AttributeMappingController::class, 'importExcel']);

    // =====================================================================
    // Export-Job-Steuerung (Enterprise: advanced_export)
    // =====================================================================
    Route::middleware('module:advanced_export')->group(function () {
        Route::apiResource('export-jobs', ExportJobController::class);
        Route::post('export-jobs/{export_job}/execute', [ExportJobController::class, 'execute']);
        Route::get('export-jobs/{export_job}/download', [ExportJobController::class, 'download']);
        Route::get('export-jobs/{export_job}/logs', [ExportJobController::class, 'logs']);
        Route::get('export-jobs/{export_job}/stream', [ExportJobController::class, 'stream']);

        // Export-Dateien (Filesystem Viewer)
        Route::get('export-files', [ExportFileController::class, 'index']);
        Route::delete('export-files/{name}', [ExportFileController::class, 'destroy']);
    });

    // =====================================================================
    // Agent 7: Publixx Live-API (Enterprise: publixx)
    // =====================================================================
    Route::middleware('module:publixx')->group(function () {
        Route::prefix('publixx')->group(function () {
            Route::get('datasets/{mapping_id}', [PublixxDatasetController::class, 'index']);
            Route::get('datasets/{mapping_id}/{product_id}', [PublixxDatasetController::class, 'show']);
            Route::post('datasets/{mapping_id}/pql', [PublixxDatasetController::class, 'pql']);
            Route::post('webhook', [PublixxDatasetController::class, 'webhook']);
        });
    });

    // =====================================================================
    // Admin: Reset Data Model
    // =====================================================================
    Route::get('admin/reset-categories', [ResetDataController::class, 'categories']);
    Route::post('admin/reset-data', ResetDataController::class);
    Route::post('admin/load-demo-data', LoadDemoDataController::class);

    // Admin: Test Data Generator (DEV)
    Route::post('admin/test-data/generate', [TestDataGeneratorController::class, 'generate']);
    Route::delete('admin/test-data', [TestDataGeneratorController::class, 'cleanup']);
    Route::get('admin/test-data/stats', [TestDataGeneratorController::class, 'stats']);
    Route::get('admin/test-data/progress', [TestDataGeneratorController::class, 'progress']);
    Route::post('admin/test-data/cancel', [TestDataGeneratorController::class, 'cancel']);

    // Admin: Offline Catalog Export
    Route::post('admin/offline-catalog/generate', [OfflineCatalogController::class, 'generate']);
    Route::get('admin/offline-catalog/progress', [OfflineCatalogController::class, 'progress']);
    Route::post('admin/offline-catalog/cancel', [OfflineCatalogController::class, 'cancel']);
    Route::delete('admin/offline-catalog/cleanup', [OfflineCatalogController::class, 'cleanup']);
    // Download/Preview-Routen sind außerhalb der Auth-Gruppe (Token via Query-Parameter)
    Route::post('admin/offline-catalog/build-bundle', [OfflineCatalogController::class, 'buildBundle']);
    Route::get('admin/offline-catalog/bundle-status', [OfflineCatalogController::class, 'bundleStatus']);
    Route::get('admin/offline-catalog/status', [OfflineCatalogController::class, 'status']);
    Route::put('settings/catalog-theme', [SettingController::class, 'updateCatalogTheme']);
    Route::get('settings/configured-plugins', [SettingController::class, 'configuredPlugins']);
    Route::get('settings/connector-credentials', [SettingController::class, 'connectorCredentials']);
    Route::put('settings/connector-credentials', [SettingController::class, 'updateConnectorCredentials']);
    Route::post('admin/search-reindex', [SettingController::class, 'reindexSearch']);
    Route::get('admin/search-reindex/progress', [SettingController::class, 'reindexProgress']);
    Route::post('admin/search-reindex/cancel', [SettingController::class, 'cancelReindex']);
    Route::get('admin/env-info', [SystemInfoController::class, 'envInfo']);
    Route::get('admin/system-status', [SystemInfoController::class, 'systemStatus']);
    Route::get('admin/queue-jobs', [SystemInfoController::class, 'queueJobs']);
    Route::post('admin/queue-flush', [SystemInfoController::class, 'queueFlush']);
    Route::post('admin/queue-cancel-job', [SystemInfoController::class, 'queueCancelJob']);
    Route::delete('admin/failed-jobs', [SystemInfoController::class, 'flushFailedJobs']);
    Route::post('admin/restart-apache', [SystemInfoController::class, 'restartApache']);
    Route::post('admin/restart-horizon', [SystemInfoController::class, 'restartHorizon']);
    Route::get('admin/system-processes', [SystemInfoController::class, 'systemProcesses']);
    Route::post('admin/pdf/batch-process', [PdfController::class, 'batchProcess']);

    // =====================================================================
    // Report Designer (Enterprise: reports)
    // =====================================================================
    Route::middleware('module:reports')->group(function () {
        Route::get('report-templates/fields', [ReportTemplateController::class, 'fields']);
        Route::apiResource('report-templates', ReportTemplateController::class);
        Route::get('report-templates/{report_template}/dependencies', [ReportTemplateController::class, 'dependencies']);
        Route::post('report-templates/{report_template}/execute', [ReportTemplateController::class, 'execute']);
        Route::post('report-templates/{report_template}/preview', [ReportTemplateController::class, 'preview']);
        Route::get('report-jobs/{report_job}', [ReportTemplateController::class, 'jobStatus']);
        Route::get('report-jobs/{report_job}/download', [ReportTemplateController::class, 'jobDownload']);
    });

    // =====================================================================
    // PDF Template Designer (Enterprise: pdf_templates)
    // =====================================================================
    Route::middleware('module:pdf_templates')->group(function () {
        Route::get('pdf-templates/fields', [PdfTemplateController::class, 'fields']);
        Route::apiResource('pdf-templates', PdfTemplateController::class);
        Route::post('pdf-templates/{pdf_template}/resolve-preview', [PdfTemplateController::class, 'resolvePreview']);
        Route::post('pdf-templates/{pdf_template}/preview', [PdfTemplateController::class, 'preview']);
        Route::post('pdf-templates/{pdf_template}/execute', [PdfTemplateController::class, 'execute']);
    });

    // =====================================================================
    // API Designer (Enterprise: api_designer)
    // =====================================================================
    Route::middleware('module:api_designer')->group(function () {
        Route::get('api-templates/fields', [ApiTemplateController::class, 'fields']);
        Route::apiResource('api-templates', ApiTemplateController::class);
        Route::get('api-templates/{api_template}/dependencies', [ApiTemplateController::class, 'dependencies']);
        Route::post('api-templates/{api_template}/preview', [ApiTemplateController::class, 'preview']);
        Route::post('api-templates/{api_template}/regenerate-key', [ApiTemplateController::class, 'regenerateApiKey']);
    });

    // =====================================================================
    // Excel Sheet Designer
    // =====================================================================
    Route::middleware('module:excel_designer')->prefix('excel-templates')->group(function () {
        Route::get('fields', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'fields']);
        Route::get('/', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'store']);
        Route::post('import', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'import']);
        // Export-Aktionen (vor {id} registrieren, sonst Route-Conflict)
        Route::get('export-progress/{exportKey}', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'exportProgress']);
        Route::post('export-cancel/{exportKey}', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'cancelExport']);
        Route::get('export-download/{exportKey}', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'downloadExport']);
        // Template-CRUD (Wildcard {id} muss nach statischen Routen kommen)
        Route::get('{id}', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'show']);
        Route::put('{id}', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'update']);
        Route::delete('{excelTemplate}', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'destroy']);
        Route::post('{id}/download', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'download']);
        Route::post('{id}/preview', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'preview']);
        Route::post('{id}/export', [\App\Http\Controllers\Api\V1\ExcelTemplateController::class, 'startExport']);
    });

    // =====================================================================
    // Catalog Templates
    // =====================================================================
    Route::get('catalog-templates/presets', [CatalogTemplateController::class, 'presets']);
    Route::apiResource('catalog-templates', CatalogTemplateController::class);
    Route::post('catalog-templates/{catalog_template}/duplicate', [CatalogTemplateController::class, 'duplicate']);
    Route::get('catalog-templates/{catalog_template}/preview', [CatalogTemplateController::class, 'preview']);

    // =====================================================================
    // Translation Jobs
    // =====================================================================
    Route::prefix('translation-jobs')->group(function () {
        Route::get('/', [TranslationJobController::class, 'index']);
        Route::post('/', [TranslationJobController::class, 'store']);
        Route::get('/{id}', [TranslationJobController::class, 'show'])->where('id', '[a-f0-9\-]{36}');
        Route::post('/{id}/submit', [TranslationJobController::class, 'submit'])->where('id', '[a-f0-9\-]{36}');
        Route::post('/{id}/approve', [TranslationJobController::class, 'approve'])->where('id', '[a-f0-9\-]{36}');
        Route::post('/{id}/cancel', [TranslationJobController::class, 'cancel'])->where('id', '[a-f0-9\-]{36}');
        Route::post('/{id}/retry', [TranslationJobController::class, 'retry'])->where('id', '[a-f0-9\-]{36}');
        Route::delete('/{id}', [TranslationJobController::class, 'destroy'])->where('id', '[a-f0-9\-]{36}');
    });

    // =====================================================================
    // TMS: Translation Management
    // =====================================================================
    Route::prefix('tms')->group(function () {
        Route::get('units', [TmsProxyController::class, 'units']);
        Route::get('units/{id}', [TmsProxyController::class, 'unit'])->where('id', '[a-f0-9\-]{36}');
        Route::put('units/{id}/translations/{lang}', [TmsProxyController::class, 'updateTranslation'])
            ->where(['id' => '[a-f0-9\-]{36}', 'lang' => '[a-z]{2,5}']);
        Route::get('stats', [TmsProxyController::class, 'stats']);
        Route::get('missing', [TmsProxyController::class, 'missing']);
        Route::post('retranslate', [TmsProxyController::class, 'retranslate']);
        Route::post('ingest', [TmsProxyController::class, 'triggerIngest']);
        Route::post('sync', [TmsProxyController::class, 'syncToDatabase']);
        Route::delete('translations', [TmsProxyController::class, 'deleteTranslations']);
        Route::delete('units', [TmsProxyController::class, 'purgeUnits']);
    });

    // =====================================================================
    // Admin: Deployment (nur Admin-Rolle)
    // =====================================================================
    Route::prefix('admin')->group(function () {
        Route::get('deploy/status', [DeploymentController::class, 'status']);
        Route::post('deploy', [DeploymentController::class, 'deploy']);
        Route::post('deploy/rollback', [DeploymentController::class, 'rollback']);
    });

    // =====================================================================
    // Admin: Test Runner (anyPIM Quality Assurance)
    // =====================================================================
    Route::get('admin/test-runner/info', [TestRunnerController::class, 'info']);
    Route::post('admin/test-runner/run', [TestRunnerController::class, 'run']);

    // =====================================================================
    // Admin: API Tester
    // =====================================================================
    Route::get('admin/api-routes', [ApiTesterController::class, 'routes']);

    // =====================================================================
    // Admin: Database Viewer (read-only)
    // =====================================================================
    Route::get('admin/db/tables', [DatabaseViewerController::class, 'tables']);
    Route::get('admin/db/tables/{table}/columns', [DatabaseViewerController::class, 'columns']);
    Route::get('admin/db/tables/{table}/rows', [DatabaseViewerController::class, 'rows']);

    // =====================================================================
    // Admin: Database Consistency
    // =====================================================================
    Route::get('admin/db-consistency/check', [DatabaseConsistencyController::class, 'check']);
    Route::post('admin/db-consistency/fix/{issueType}', [DatabaseConsistencyController::class, 'fix']);

    // =====================================================================
    // Scheduled Actions & Calendar
    // =====================================================================
    Route::apiResource('scheduled-actions', ScheduledActionController::class);
    Route::get('products/{product}/scheduled-actions', [ScheduledActionController::class, 'forProduct']);
    Route::get('calendar', [CalendarController::class, 'index']);

    // =====================================================================
    // Teams (Enterprise: workflow)
    // =====================================================================
    Route::apiResource('teams', TeamController::class);
    Route::get('teams/{team}/dependencies', [TeamController::class, 'dependencies']);

    // =====================================================================
    // Projects (Enterprise: workflow)
    // =====================================================================
    Route::apiResource('projects', ProjectController::class);
    Route::get('projects/{project}/dependencies', [ProjectController::class, 'dependencies']);
    Route::post('projects/{project}/bulk-add-products', [ProjectController::class, 'bulkAddProducts']);

    // =====================================================================
    // Workflow Statuses (Enterprise: workflow)
    // =====================================================================
    Route::apiResource('workflow-statuses', WorkflowStatusController::class);
    Route::get('workflow-statuses/{workflow_status}/dependencies', [WorkflowStatusController::class, 'dependencies']);

    // =====================================================================
    // Workflows (Enterprise: workflow)
    // =====================================================================
    Route::apiResource('workflows', WorkflowController::class);
    Route::get('workflows/{workflow}/dependencies', [WorkflowController::class, 'dependencies']);

    // =====================================================================
    // Workflow Tasks (Enterprise: workflow)
    // =====================================================================
    Route::apiResource('workflow-tasks', WorkflowTaskController::class);

    // =====================================================================
    // Dashboard
    // =====================================================================
    Route::get('dashboard', [DashboardController::class, 'index']);

    // =====================================================================
    // Connectors (Enterprise: connectors)
    // =====================================================================
    Route::middleware('module:connectors')->prefix('connectors')->group(function () {
        Route::get('/', [ConnectorController::class, 'index']);
        Route::get('/connections', [ConnectorController::class, 'connections']);
        Route::post('/connections', [ConnectorController::class, 'store']);
        Route::get('/connections/{connection}', [ConnectorController::class, 'showConnection']);
        Route::delete('/connections/{connection}', [ConnectorController::class, 'destroy']);

        // OAuth Flow
        Route::get('/{type}/authorize', [ConnectorController::class, 'startAuthorization'])
            ->where('type', '[a-z0-9_-]+');
        Route::post('/{type}/callback', [ConnectorController::class, 'callback'])
            ->where('type', '[a-z0-9_-]+');

        // Asset-Sync
        Route::post('/connections/{connection}/sync-media', [ConnectorController::class, 'syncMedia']);
        Route::post('/connections/{connection}/sync-media-bulk', [ConnectorController::class, 'syncMediaBulk']);

        // Produktdaten-Sync
        Route::post('/connections/{connection}/sync-product', [ConnectorController::class, 'syncProduct']);
        Route::post('/connections/{connection}/sync-product-bulk', [ConnectorController::class, 'syncProductBulk']);

        // Profil-basierter Sync (Shopware)
        Route::post('/connections/{connection}/sync-profile', [ConnectorController::class, 'syncFromProfile']);
        Route::post('/connections/{connection}/sync-hierarchy', [ConnectorController::class, 'syncHierarchy']);

        // Vorschau/Dry Run
        Route::post('/connections/{connection}/preview-product', [ConnectorController::class, 'previewProduct']);

        // Verbindung aktualisieren (Settings + Export-Profil)
        Route::put('/connections/{connection}', [ConnectorController::class, 'update']);

        // Vorschau-Profile für Connector-Konfiguration
        Route::get('/website-profiles', [ConnectorController::class, 'websiteProfiles']);

        // Logs
        Route::get('/connections/{connection}/sync-logs', [ConnectorController::class, 'syncLogs']);
        Route::delete('/connections/{connection}/sync-logs', [ConnectorController::class, 'clearSyncLogs']);

        // Canva Export-Profile (CRUD + Execute)
        Route::apiResource('canva-export-profiles', CanvaExportProfileController::class)
            ->parameters(['canva-export-profiles' => 'canvaExportProfile']);
        Route::post('canva-export-profiles/{canvaExportProfile}/execute', [CanvaExportProfileController::class, 'execute']);
    });
});
