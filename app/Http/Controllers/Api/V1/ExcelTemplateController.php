<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Traits\ChecksDeletionConstraints;
use App\Jobs\ExcelExportJob;
use App\Models\Attribute;
use App\Models\ExcelTemplate;
use App\Models\MediaUsageType;
use App\Models\PriceType;
use App\Models\ProductRelationType;
use App\Models\SearchProfile;
use App\Services\ExcelDesigner\ExcelDesignerService;
use App\Services\ExcelDesigner\ExcelTemplateImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelTemplateController extends Controller
{
    use ChecksDeletionConstraints;

    public function __construct(
        private readonly ExcelDesignerService $excelDesignerService,
        private readonly ExcelTemplateImporter $templateImporter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $templates = ExcelTemplate::query()
            ->when($userId, fn ($q) => $q->visibleTo($userId))
            ->with('searchProfile')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|nullable',
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'template_json' => 'required|array',
            'excel_settings' => 'sometimes|array|nullable',
            'language' => 'sometimes|string|max:5',
            'is_shared' => 'sometimes|boolean',
        ]);

        $validated['user_id'] = $request->user()?->id;

        $template = ExcelTemplate::create($validated);

        return response()->json([
            'data' => $template->load('searchProfile'),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $template = ExcelTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        return response()->json([
            'data' => $template->load('searchProfile'),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $template = ExcelTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'template_json' => 'sometimes|array',
            'excel_settings' => 'sometimes|array|nullable',
            'language' => 'sometimes|string|max:5',
            'is_shared' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return response()->json(['data' => $template->fresh()->load('searchProfile')]);
    }

    public function destroy(Request $request, ExcelTemplate $excelTemplate): JsonResponse
    {
        $this->authorizeAccess($request, $excelTemplate);

        return $this->destroyWithConstraintCheck($request, $excelTemplate);
    }

    /**
     * GET /api/v1/excel-templates/fields — Verfügbare Felder für den Designer.
     *
     * Erweitert um Preistypen, Medien-Verwendungstypen, Relationstypen.
     */
    public function fields(): JsonResponse
    {
        // Stammfelder
        $baseFields = [
            ['field' => 'sku', 'label_de' => 'Artikelnummer', 'label_en' => 'SKU', 'category' => 'base'],
            ['field' => 'name', 'label_de' => 'Produktname', 'label_en' => 'Product Name', 'category' => 'base'],
            ['field' => 'ean', 'label_de' => 'EAN', 'label_en' => 'EAN', 'category' => 'base'],
            ['field' => 'status', 'label_de' => 'Status', 'label_en' => 'Status', 'category' => 'base'],
            ['field' => 'product_type', 'label_de' => 'Produkttyp', 'label_en' => 'Product Type', 'category' => 'base'],
            ['field' => 'hierarchy_node', 'label_de' => 'Kategorie', 'label_en' => 'Category', 'category' => 'base'],
            ['field' => 'created_at', 'label_de' => 'Erstellt am', 'label_en' => 'Created at', 'category' => 'base'],
            ['field' => 'updated_at', 'label_de' => 'Geändert am', 'label_en' => 'Updated at', 'category' => 'base'],
            ['field' => 'manufacturer', 'label_de' => 'Hersteller', 'label_en' => 'Manufacturer', 'category' => 'base'],
            ['field' => 'parent_sku', 'label_de' => 'Parent-SKU', 'label_en' => 'Parent SKU', 'category' => 'base'],
            ['field' => 'parent_name', 'label_de' => 'Parent-Name', 'label_en' => 'Parent Name', 'category' => 'base'],
            ['field' => 'product_type_ref', 'label_de' => 'Produkttyp-Referenz', 'label_en' => 'Product Type Ref', 'category' => 'base'],
        ];

        // Attribute
        $attributes = Attribute::query()
            ->select(['id', 'technical_name', 'name_de', 'name_en', 'data_type', 'attribute_type_id'])
            ->with('attributeType:id,name_de,name_en')
            ->where('data_type', '!=', 'Composite')
            ->orderBy('name_de')
            ->get()
            ->map(fn ($attr) => [
                'attributeId' => $attr->id,
                'technical_name' => $attr->technical_name,
                'label_de' => $attr->name_de,
                'label_en' => $attr->name_en,
                'data_type' => $attr->data_type,
                'category' => 'attribute',
                'group_de' => $attr->attributeType?->name_de,
                'group_en' => $attr->attributeType?->name_en,
            ]);

        // Preistypen
        $priceTypes = PriceType::orderBy('name_de')->get()->map(fn ($pt) => [
            'priceType' => $pt->technical_name,
            'label_de' => $pt->name_de,
            'label_en' => $pt->name_en,
            'category' => 'price',
        ]);

        // Medien-Verwendungstypen
        $mediaUsageTypes = MediaUsageType::orderBy('sort_order')->get()->map(fn ($mut) => [
            'mediaUsageType' => $mut->technical_name,
            'label_de' => $mut->name_de,
            'label_en' => $mut->name_en,
            'category' => 'media',
        ]);

        // Relationstypen
        $relationTypes = ProductRelationType::orderBy('name_de')->get()->map(fn ($rt) => [
            'relationType' => $rt->technical_name,
            'label_de' => $rt->name_de,
            'label_en' => $rt->name_en,
            'category' => 'relation',
        ]);

        // Gruppierungsfelder
        $groupFields = [
            ['field' => 'product_type', 'label_de' => 'Produkttyp', 'label_en' => 'Product Type'],
            ['field' => 'master_hierarchy_node', 'label_de' => 'Hierarchieknoten', 'label_en' => 'Hierarchy Node'],
            ['field' => 'status', 'label_de' => 'Status', 'label_en' => 'Status'],
            ['field' => 'none', 'label_de' => 'Keine Gruppierung', 'label_en' => 'No Grouping'],
        ];

        return response()->json([
            'data' => [
                'base_fields' => $baseFields,
                'attributes' => $attributes,
                'price_types' => $priceTypes,
                'media_usage_types' => $mediaUsageTypes,
                'relation_types' => $relationTypes,
                'group_fields' => $groupFields,
            ],
        ]);
    }

    /**
     * POST /api/v1/excel-templates/{id}/preview — Vorschau mit begrenzten Produkten.
     */
    public function preview(Request $request, string $id): JsonResponse
    {
        $template = ExcelTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $searchProfile = isset($validated['search_profile_id'])
            ? SearchProfile::find($validated['search_profile_id'])
            : null;

        $result = $this->excelDesignerService->preview(
            $template,
            $searchProfile,
            $validated['limit'] ?? 5,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/v1/excel-templates/{id}/download — Excel-Datei herunterladen.
     */
    public function download(Request $request, string $id): StreamedResponse
    {
        $template = ExcelTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $searchProfile = null;
        if ($request->filled('search_profile_id')) {
            $searchProfile = SearchProfile::find($request->input('search_profile_id'));
        }

        return $this->excelDesignerService->download($template, $searchProfile);
    }

    /**
     * POST /api/v1/excel-templates/import — Excel-Vorlage hochladen und Template erstellen.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'name' => 'sometimes|string|max:255',
        ]);

        $templateJson = $this->templateImporter->import($request->file('file'));

        // Optional: Template direkt speichern
        if ($request->filled('name')) {
            $template = ExcelTemplate::create([
                'name' => $request->input('name'),
                'template_json' => $templateJson,
                'language' => 'de',
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'data' => $template,
                'template_json' => $templateJson,
            ], 201);
        }

        // Nur die extrahierte Struktur zurückgeben
        return response()->json([
            'data' => $templateJson,
        ]);
    }

    /**
     * POST /api/v1/excel-templates/{id}/export — Async-Export starten.
     */
    public function startExport(Request $request, string $id): JsonResponse
    {
        $template = ExcelTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
        ]);

        $searchProfileId = $validated['search_profile_id'] ?? null;
        $exportKey = 'excel_' . Str::random(16);
        $userId = $request->user()?->id;

        ExcelExportJob::dispatch($template->id, $searchProfileId, $exportKey);

        // User-ID im Cache speichern für Auth-Check bei Progress/Download (TTL = Export-TTL)
        Cache::put(ExcelExportJob::cacheKey($exportKey) . ':owner', $userId, 3600);

        return response()->json([
            'data' => [
                'export_key' => $exportKey,
                'status' => 'queued',
            ],
        ]);
    }

    /**
     * GET /api/v1/excel-templates/export-progress/{exportKey} — Progress abfragen.
     */
    public function exportProgress(Request $request, string $exportKey): JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        $progress = Cache::get(ExcelExportJob::cacheKey($exportKey));

        if (!$progress) {
            return response()->json([
                'data' => ['status' => 'queued', 'processed' => 0, 'total' => 0, 'percent' => 0],
            ]);
        }

        return response()->json(['data' => $progress]);
    }

    /**
     * POST /api/v1/excel-templates/export-cancel/{exportKey} — Export abbrechen.
     */
    public function cancelExport(Request $request, string $exportKey): JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        ExcelExportJob::cancel($exportKey);

        return response()->json(['data' => ['status' => 'cancelling']]);
    }

    /**
     * GET /api/v1/excel-templates/export-download/{exportKey} — Fertige Datei herunterladen.
     */
    public function downloadExport(Request $request, string $exportKey): BinaryFileResponse|JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        $progress = Cache::get(ExcelExportJob::cacheKey($exportKey));

        if (!$progress || $progress['status'] !== 'completed') {
            return response()->json(['error' => 'Export nicht bereit.'], 404);
        }

        $path = $progress['output_path'] ?? null;
        if (!$path || !file_exists($path)) {
            return response()->json(['error' => 'Export-Datei nicht gefunden.'], 404);
        }

        // Path-Traversal verhindern
        $realPath = realpath($path);
        $allowedDir = storage_path('app/exports');
        if (!$realPath || !str_starts_with($realPath, $allowedDir)) {
            return response()->json(['error' => 'Ungültiger Export-Pfad.'], 403);
        }

        $fileName = $progress['file_name'] ?? 'export.xlsx';

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function authorizeAccess(Request $request, ExcelTemplate $template): void
    {
        $userId = $request->user()?->id;
        if (!$template->is_shared && $template->user_id && $userId !== $template->user_id) {
            abort(403, 'Kein Zugriff auf dieses Excel-Template.');
        }
    }

    private function authorizeExportAccess(Request $request, string $exportKey): void
    {
        $ownerId = Cache::get(ExcelExportJob::cacheKey($exportKey) . ':owner');
        $userId = $request->user()?->id;

        // Deny wenn Owner unbekannt (Cache abgelaufen) oder User nicht übereinstimmt
        if ($ownerId === null || $userId !== $ownerId) {
            abort(403, 'Kein Zugriff auf diesen Export.');
        }
    }
}
