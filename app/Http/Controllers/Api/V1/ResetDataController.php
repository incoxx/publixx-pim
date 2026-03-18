<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetDataController extends Controller
{
    /**
     * Categories mapped to the tables they require to be truncated.
     *
     * Order inside each group does NOT matter because we disable FK checks.
     */
    private const CATEGORY_TABLES = [
        'products' => [
            'product_attribute_values',
            'variant_inheritance_rules',
            'product_relations',
            'product_relation_attribute_values',
            'product_media_assignments',
            'product_prices',
            'output_hierarchy_product_assignments',
            'products_search_index',
            'watchlist_items',
            'products',
        ],
        'hierarchies' => [
            'hierarchy_node_attribute_assignments',
            'hierarchy_node_attribute_values',
            'hierarchy_nodes',
            'output_hierarchy_product_assignments',
            'hierarchies',
        ],
        'attributes' => [
            'product_attribute_values',
            'hierarchy_node_attribute_assignments',
            'hierarchy_node_attribute_values',
            'attribute_view_assignments',
            'attributes',
            'attribute_types',
            'attribute_views',
        ],
        'units' => [
            'units',
            'unit_groups',
        ],
        'value_lists' => [
            'value_list_entries',
            'value_lists',
        ],
        'price_types' => [
            'product_prices',
            'price_types',
        ],
        'product_types' => [
            'product_types',
        ],
        'relation_types' => [
            'product_relations',
            'product_relation_attribute_values',
            'product_relation_types',
        ],
        'comparison_operators' => [
            'comparison_operators',
            'comparison_operator_groups',
        ],
        'media' => [
            'product_media_assignments',
            'media',
        ],
        'import_export' => [
            'import_job_errors',
            'import_jobs',
            'publixx_export_mappings',
        ],
        'search_profiles' => [
            'search_profiles',
        ],
        'export_profiles' => [
            'export_profiles',
        ],
        'watchlist' => [
            'watchlist_items',
        ],
        'audit_logs' => [
            'audit_logs',
        ],
    ];

    /**
     * Categories that require special handling (not simple truncation).
     * Each entry maps a category key to a callable name on this controller.
     */
    private const SPECIAL_CATEGORIES = [
        'catalog_settings',
    ];

    /**
     * All available category keys.
     */
    public static function availableCategories(): array
    {
        return array_merge(array_keys(self::CATEGORY_TABLES), self::SPECIAL_CATEGORIES);
    }

    /**
     * GET /api/v1/admin/reset-categories
     *
     * Returns available reset categories with labels.
     */
    public function categories(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('Admin')) {
            return $this->errorResponse('forbidden', 'Forbidden', 403, 'Only administrators may access this.');
        }

        $labels = [
            'products'             => 'Produkte',
            'hierarchies'          => 'Hierarchien',
            'attributes'           => 'Attribute & Attributgruppen',
            'units'                => 'Einheiten',
            'value_lists'          => 'Wertelisten',
            'price_types'          => 'Preistypen',
            'product_types'        => 'Produkttypen',
            'relation_types'       => 'Beziehungstypen',
            'comparison_operators' => 'Vergleichsoperatoren',
            'media'                => 'Medien',
            'import_export'        => 'Import-/Export-Daten',
            'search_profiles'      => 'Suchprofile',
            'export_profiles'      => 'Exportprofile',
            'watchlist'            => 'Merkliste',
            'catalog_settings'     => 'Vorschaukatalog Einstellungen',
            'audit_logs'           => 'Journal / Audit-Logs',
        ];

        $categories = [];
        foreach ($labels as $key => $label) {
            $categories[] = [
                'key'    => $key,
                'label'  => $label,
                'tables' => self::CATEGORY_TABLES[$key] ?? [],
            ];
        }

        return $this->successResponse($categories);
    }

    /**
     * POST /api/v1/admin/reset-data
     *
     * Reset selected PIM data categories.
     * Requires confirmation phrase and selected categories.
     * If no categories are provided, resets ALL data-model tables (legacy behaviour).
     */
    public function __invoke(Request $request): JsonResponse
    {
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
            'categories'   => ['sometimes', 'array'],
            'categories.*' => ['string', 'in:' . implode(',', self::availableCategories())],
        ]);

        $selectedCategories = $request->input('categories', self::availableCategories());

        if (empty($selectedCategories)) {
            return $this->errorResponse(
                'validation',
                'No categories selected',
                422,
                'Bitte wählen Sie mindestens eine Kategorie zum Zurücksetzen aus.'
            );
        }

        try {
            // Collect all tables from selected categories (deduplicated)
            $tables = [];
            foreach ($selectedCategories as $category) {
                foreach (self::CATEGORY_TABLES[$category] ?? [] as $table) {
                    $tables[$table] = true;
                }
            }
            $tables = array_keys($tables);

            DB::transaction(function () use ($tables, $selectedCategories) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');

                foreach ($tables as $table) {
                    DB::table($table)->truncate();
                }

                // Handle special categories that need conditional deletes
                if (in_array('catalog_settings', $selectedCategories, true)) {
                    Setting::where('group', 'catalog_theme')->delete();
                }

                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            });

            Log::info('Selective data model reset by admin', [
                'user_id'    => $request->user()->id,
                'user_email' => $request->user()->email,
                'categories' => $selectedCategories,
                'tables'     => $tables,
            ]);

            return $this->successResponse([
                'message'    => 'Ausgewählte Daten wurden erfolgreich zurückgesetzt.',
                'categories' => $selectedCategories,
                'tables'     => $tables,
            ]);
        } catch (\Throwable $e) {
            Log::error('Data model reset failed', [
                'user_id'    => $request->user()->id,
                'error'      => $e->getMessage(),
                'categories' => $selectedCategories,
            ]);

            return $this->errorResponse(
                'server_error',
                'Reset failed',
                500,
                'Fehler beim Zurücksetzen: ' . $e->getMessage()
            );
        }
    }
}
