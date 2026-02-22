<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AssetCatalogController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\AttributeController;
use App\Http\Controllers\Api\V1\AttributeTypeController;
use App\Http\Controllers\Api\V1\AttributeViewController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DebugController;
use App\Http\Controllers\Api\V1\DeploymentController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\LoadDemoDataController;
use App\Http\Controllers\Api\V1\HierarchyController;
use App\Http\Controllers\Api\V1\HierarchyNodeAttributeValueController;
use App\Http\Controllers\Api\V1\HierarchyNodeController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\MediaAttributeValueController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MediaUsageTypeController;
use App\Http\Controllers\Api\V1\NodeAttributeAssignmentController;
use App\Http\Controllers\Api\V1\PqlController;
use App\Http\Controllers\Api\V1\PriceTypeController;
use App\Http\Controllers\Api\V1\ProductTypeController;
use App\Http\Controllers\Api\V1\ProductAttributeValueController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductMediaController;
use App\Http\Controllers\Api\V1\ProductPriceController;
use App\Http\Controllers\Api\V1\ProductRelationAttributeValueController;
use App\Http\Controllers\Api\V1\ProductRelationController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\ProductVersionController;
use App\Http\Controllers\Api\V1\PublixxDatasetController;
use App\Http\Controllers\Api\V1\PxfTemplateController;
use App\Http\Controllers\Api\V1\RelationTypeController;
use App\Http\Controllers\Api\V1\ResetDataController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitGroupController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ValueListController;
use App\Http\Controllers\Api\V1\ValueListEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Publixx PIM — Merged API Routes
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
// Public media file serving (no auth — used by <img src> tags)
// =========================================================================
Route::prefix('v1')->middleware('throttle.pim')->group(function () {
    Route::get('media/file/{filename}', [MediaController::class, 'serve'])->name('media.serve');
    Route::get('media/thumb/{medium}', [MediaController::class, 'thumb'])->name('media.thumb');
});

// =========================================================================
// Public Asset Catalog API (no auth required)
// =========================================================================
Route::prefix('v1/asset-catalog')->middleware('throttle.pim')->group(function () {
    Route::get('assets', [AssetCatalogController::class, 'assets']);
    Route::get('assets/{medium}', [AssetCatalogController::class, 'asset']);
    Route::get('folders', [AssetCatalogController::class, 'folders']);
    Route::post('download', [AssetCatalogController::class, 'download']);
});

// =========================================================================
// Public Catalog API (no auth required)
// =========================================================================
Route::prefix('v1/catalog')->middleware('throttle.pim')->group(function () {
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('products/export.json', [CatalogController::class, 'productsExportJson']);
    Route::get('products/{product}', [CatalogController::class, 'product']);
    Route::get('products/{product}/json', [CatalogController::class, 'productJson']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('media/{filename}', [CatalogController::class, 'media'])->name('catalog.media');
});

// =========================================================================
// Debug: Log access (no auth — test server only)
// =========================================================================
Route::prefix('v1/debug')->middleware('throttle.pim')->group(function () {
    Route::get('logs', [DebugController::class, 'logs']);
    Route::get('logs/clear', [DebugController::class, 'clearLogs']);
    Route::delete('logs', [DebugController::class, 'clearLogs']);
});

// =========================================================================
// Agent 2: Auth (authenticated)
// =========================================================================
Route::prefix('v1/auth')->middleware(['auth:sanctum', 'throttle.pim'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);
});

// =========================================================================
// All authenticated routes
// =========================================================================
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle.pim'])->group(function () {

    // =====================================================================
    // Agent 2: User & Role Management
    // =====================================================================
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

    // =====================================================================
    // Agent 3: Attributes
    // =====================================================================
    Route::apiResource('attributes', AttributeController::class);

    // =====================================================================
    // Agent 3: Attribute Types
    // =====================================================================
    Route::apiResource('attribute-types', AttributeTypeController::class);

    // =====================================================================
    // Agent 3: Unit Groups & Units
    // =====================================================================
    Route::apiResource('unit-groups', UnitGroupController::class);
    Route::apiResource('unit-groups.units', UnitController::class)->shallow();

    // =====================================================================
    // Agent 3: Value Lists & Entries
    // =====================================================================
    Route::apiResource('value-lists', ValueListController::class);
    Route::apiResource('value-lists.entries', ValueListEntryController::class)->shallow();

    // =====================================================================
    // Agent 3: Attribute Views & Assignments
    // =====================================================================
    Route::apiResource('attribute-views', AttributeViewController::class);
    Route::post('attribute-views/{attribute_view}/attributes', [AttributeViewController::class, 'assignAttribute']);
    Route::delete('attribute-views/{attribute_view}/attributes/{attribute}', [AttributeViewController::class, 'removeAttribute']);

    // =====================================================================
    // Agent 3: Product Types
    // =====================================================================
    Route::apiResource('product-types', ProductTypeController::class);
    Route::get('product-types/{product_type}/schema', [ProductTypeController::class, 'schema']);

    // =====================================================================
    // Agent 3: Hierarchies
    // =====================================================================
    Route::apiResource('hierarchies', HierarchyController::class);
    Route::get('hierarchies/{hierarchy}/tree', [HierarchyController::class, 'tree']);
    Route::apiResource('hierarchies.nodes', HierarchyNodeController::class)
        ->shallow()
        ->parameters(['nodes' => 'hierarchy_node']);
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
    // Agent 3: Products
    // =====================================================================
    Route::apiResource('products', ProductController::class);

    // Product Preview (generic, no PXF)
    Route::get('products/{product}/preview', [ProductController::class, 'preview']);
    Route::get('products/{product}/preview/export.xlsx', [ProductController::class, 'previewExportExcel']);
    Route::get('products/{product}/preview/export.pdf', [ProductController::class, 'previewExportPdf']);
    Route::get('products/{product}/completeness', [ProductController::class, 'completeness']);

    // Product Attribute Values
    Route::get('products/{product}/attribute-values', [ProductAttributeValueController::class, 'index']);
    Route::get('products/{product}/resolved-attributes', [ProductAttributeValueController::class, 'resolved']);
    Route::put('products/{product}/attribute-values', [ProductAttributeValueController::class, 'bulkUpdate']);

    // Product Variants
    Route::get('products/{product}/variants', [ProductVariantController::class, 'index']);
    Route::post('products/{product}/variants', [ProductVariantController::class, 'store']);
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
    // Agent 3: Media
    // =====================================================================
    Route::get('media/diagnostics', [MediaController::class, 'diagnostics']);
    Route::apiResource('media', MediaController::class);
    // media/file/{filename} and media/thumb/{medium} are registered outside auth group (public access)
    Route::get('media/{medium}/attribute-values', [MediaAttributeValueController::class, 'index']);
    Route::put('media/{medium}/attribute-values', [MediaAttributeValueController::class, 'bulkUpdate']);

    Route::apiResource('media-usage-types', MediaUsageTypeController::class);

    Route::get('products/{product}/media', [ProductMediaController::class, 'index']);
    Route::post('products/{product}/media', [ProductMediaController::class, 'store']);
    Route::delete('product-media/{product_medium}', [ProductMediaController::class, 'destroy']);

    // =====================================================================
    // Agent 3: Prices
    // =====================================================================
    Route::apiResource('price-types', PriceTypeController::class);
    Route::get('products/{product}/prices', [ProductPriceController::class, 'index']);
    Route::post('products/{product}/prices', [ProductPriceController::class, 'store']);
    Route::put('product-prices/{product_price}', [ProductPriceController::class, 'update']);
    Route::delete('product-prices/{product_price}', [ProductPriceController::class, 'destroy']);

    // =====================================================================
    // Agent 3: Relations
    // =====================================================================
    Route::apiResource('relation-types', RelationTypeController::class);
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
    Route::delete('imports/{import}', [ImportController::class, 'destroy']);

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
    // Agent 7: Publixx Live-API
    // =====================================================================
    Route::prefix('publixx')->group(function () {
        Route::get('datasets/{mapping_id}', [PublixxDatasetController::class, 'index']);
        Route::get('datasets/{mapping_id}/{product_id}', [PublixxDatasetController::class, 'show']);
        Route::post('datasets/{mapping_id}/pql', [PublixxDatasetController::class, 'pql']);
        Route::post('webhook', [PublixxDatasetController::class, 'webhook']);
    });

    // =====================================================================
    // Admin: Reset Data Model
    // =====================================================================
    Route::post('admin/reset-data', ResetDataController::class);
    Route::post('admin/load-demo-data', LoadDemoDataController::class);

    // =====================================================================
    // PXF Templates
    // =====================================================================
    Route::post('pxf-templates/import', [PxfTemplateController::class, 'import']);
    Route::apiResource('pxf-templates', PxfTemplateController::class);
    Route::get('pxf-templates/{pxf_template}/preview/{product}', [PxfTemplateController::class, 'preview']);

    // =====================================================================
    // Admin: Deployment (nur Admin-Rolle)
    // =====================================================================
    Route::prefix('admin')->group(function () {
        Route::get('deploy/status', [DeploymentController::class, 'status']);
        Route::post('deploy', [DeploymentController::class, 'deploy']);
        Route::post('deploy/rollback', [DeploymentController::class, 'rollback']);
    });
});
