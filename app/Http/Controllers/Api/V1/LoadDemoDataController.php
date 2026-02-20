<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Import\DemoTemplateGenerator;
use App\Services\Import\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LoadDemoDataController extends Controller
{
    /**
     * POST /api/v1/admin/load-demo-data
     *
     * 1. Alle bestehenden Daten löschen (wie ResetDataController)
     * 2. Demo-Excel generieren
     * 3. Über den ImportService importieren
     */
    public function __invoke(
        Request $request,
        DemoTemplateGenerator $generator,
        ImportService $importService,
    ): JsonResponse {
        if (! $request->user()->hasRole('Admin')) {
            return $this->errorResponse(
                'forbidden',
                'Forbidden',
                403,
                'Only administrators may load demo data.',
            );
        }

        $startTime = microtime(true);

        try {
            // ── Schritt 1: Alle Daten löschen ──
            DB::transaction(function () {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');

                $tables = [
                    'import_job_errors',
                    'pxf_templates',
                    'product_relation_attribute_values',
                    'publixx_export_mappings',
                    'import_jobs',
                    'product_attribute_values',
                    'variant_inheritance_rules',
                    'product_relations',
                    'product_media_assignments',
                    'product_prices',
                    'output_hierarchy_product_assignments',
                    'products_search_index',
                    'audit_logs',
                    'products',
                    'hierarchy_node_attribute_assignments',
                    'hierarchy_node_attribute_values',
                    'attributes',
                    'hierarchy_nodes',
                    'attribute_view_assignments',
                    'units',
                    'value_list_entries',
                    'comparison_operators',
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

            Log::info('Demo data: reset completed', ['user' => $request->user()->email]);

            // ── Schritt 2: Demo-Excel generieren ──
            $demoPath = Storage::disk('local')->path('imports/demo_pim_import.xlsx');
            $dir = dirname($demoPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $generator->generate($demoPath);

            Log::info('Demo data: template generated', ['path' => $demoPath]);

            // ── Schritt 3: Als UploadedFile simulieren und importieren ──
            $uploadedFile = new UploadedFile(
                $demoPath,
                'demo_pim_import.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            );

            $importJob = $importService->upload($uploadedFile, $request->user()->id);

            // Force-Execute (Validierungsfehler ignorieren bei Demo-Daten)
            $importJob = $importService->execute($importJob, force: true);

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Demo data: import completed', [
                'user' => $request->user()->email,
                'import_id' => $importJob->id,
                'status' => $importJob->status,
                'duration' => $duration,
            ]);

            return $this->successResponse([
                'success' => $importJob->status === 'completed',
                'message' => $importJob->status === 'completed'
                    ? "Demo-Daten erfolgreich geladen in {$duration}s"
                    : "Import-Status: {$importJob->status}",
                'duration_seconds' => $duration,
                'import_id' => $importJob->id,
                'import_result' => $importJob->result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Demo data load failed', [
                'user' => $request->user()->email,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'server_error',
                'Demo data load failed',
                500,
                $e->getMessage(),
            );
        }
    }
}
