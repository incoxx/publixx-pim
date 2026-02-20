<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetDataController extends Controller
{
    /**
     * Reset all PIM data (products, hierarchies, attributes and related tables).
     *
     * Admin-only. Requires confirmation phrase in the request body.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Only Admin role may call this endpoint
        if (! $request->user()->hasRole('Admin')) {
            return $this->errorResponse(
                'forbidden',
                'Forbidden',
                403,
                'Only administrators may reset the data model.'
            );
        }

        $request->validate([
            'confirmation' => ['required', 'string', 'in:RESET'],
        ]);

        try {
            DB::transaction(function () {
                // Disable FK checks for clean truncation
                DB::statement('SET FOREIGN_KEY_CHECKS=0');

                $tables = [
                    // Tier 6 — deepest dependents
                    'import_job_errors',
                    'pxf_templates',

                    // Tier 5
                    'product_relation_attribute_values',
                    'publixx_export_mappings',
                    'import_jobs',

                    // Tier 4 — product dependents
                    'product_attribute_values',
                    'variant_inheritance_rules',
                    'product_relations',
                    'product_media_assignments',
                    'product_prices',
                    'output_hierarchy_product_assignments',
                    'products_search_index',
                    'audit_logs',

                    // Tier 3
                    'products',
                    'hierarchy_node_attribute_assignments',
                    'hierarchy_node_attribute_values',

                    // Tier 2
                    'attributes',
                    'hierarchy_nodes',
                    'attribute_view_assignments',

                    // Tier 1 — reference data
                    'units',
                    'value_list_entries',
                    'comparison_operators',

                    // Tier 0 — root reference tables (data-model only)
                    'attribute_types',
                    'unit_groups',
                    'value_lists',
                    'comparison_operator_groups',
                    'product_types',
                    'price_types',
                    'product_relation_types',
                    'hierarchies',
                    'attribute_views',
                    'media',
                ];

                foreach ($tables as $table) {
                    DB::table($table)->truncate();
                }

                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            });

            Log::info('Data model reset by admin', [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
            ]);

            return $this->successResponse([
                'message' => 'Data model has been reset successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Data model reset failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'server_error',
                'Reset failed',
                500,
                'An error occurred while resetting the data model: ' . $e->getMessage()
            );
        }
    }
}
