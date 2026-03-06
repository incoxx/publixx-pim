<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ExecuteReportJob;
use App\Models\Attribute;
use App\Models\ReportJob;
use App\Models\ReportTemplate;
use App\Models\SearchProfile;
use App\Services\Report\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportTemplateController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $templates = ReportTemplate::query()
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
            'format' => 'required|string|in:docx,pdf',
            'page_orientation' => 'sometimes|string|in:portrait,landscape',
            'page_size' => 'sometimes|string|max:20',
            'language' => 'sometimes|string|max:5',
            'is_shared' => 'sometimes|boolean',
        ]);

        $validated['user_id'] = $request->user()?->id;

        $template = ReportTemplate::create($validated);

        return response()->json(['data' => $template->load('searchProfile')], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $template = ReportTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        return response()->json([
            'data' => $template->load('searchProfile'),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $template = ReportTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'template_json' => 'sometimes|array',
            'format' => 'sometimes|string|in:docx,pdf',
            'page_orientation' => 'sometimes|string|in:portrait,landscape',
            'page_size' => 'sometimes|string|max:20',
            'language' => 'sometimes|string|max:5',
            'is_shared' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return response()->json(['data' => $template->fresh()->load('searchProfile')]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $template = ReportTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);
        $template->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/report-templates/fields — Available fields for the designer palette.
     */
    public function fields(): JsonResponse
    {
        // Base fields
        $baseFields = [
            ['field' => 'sku', 'label_de' => 'Artikelnummer', 'label_en' => 'SKU', 'category' => 'base'],
            ['field' => 'name', 'label_de' => 'Produktname', 'label_en' => 'Product Name', 'category' => 'base'],
            ['field' => 'ean', 'label_de' => 'EAN', 'label_en' => 'EAN', 'category' => 'base'],
            ['field' => 'status', 'label_de' => 'Status', 'label_en' => 'Status', 'category' => 'base'],
            ['field' => 'product_type', 'label_de' => 'Produkttyp', 'label_en' => 'Product Type', 'category' => 'base'],
            ['field' => 'hierarchy_node', 'label_de' => 'Kategorie', 'label_en' => 'Category', 'category' => 'base'],
            ['field' => 'created_at', 'label_de' => 'Erstellt am', 'label_en' => 'Created at', 'category' => 'base'],
            ['field' => 'updated_at', 'label_de' => 'Geändert am', 'label_en' => 'Updated at', 'category' => 'base'],
        ];

        // Attributes
        $attributes = Attribute::query()
            ->select(['id', 'technical_name', 'name_de', 'name_en', 'data_type', 'attribute_type_id'])
            ->with('attributeType:id,name_de,name_en')
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

        // Layout elements
        $layoutElements = [
            ['type' => 'text', 'label_de' => 'Statischer Text', 'label_en' => 'Static Text', 'category' => 'layout'],
            ['type' => 'separator', 'label_de' => 'Trennlinie', 'label_en' => 'Separator', 'category' => 'layout'],
            ['type' => 'pageBreak', 'label_de' => 'Seitenumbruch', 'label_en' => 'Page Break', 'category' => 'layout'],
            ['type' => 'image', 'label_de' => 'Produktbild', 'label_en' => 'Product Image', 'category' => 'layout'],
            ['type' => 'counter', 'label_de' => 'Zähler', 'label_en' => 'Counter', 'category' => 'layout'],
        ];

        // Group field options
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
                'layout_elements' => $layoutElements,
                'group_fields' => $groupFields,
            ],
        ]);
    }

    /**
     * POST /api/v1/report-templates/{id}/execute — Generate report.
     */
    public function execute(Request $request, string $id): JsonResponse
    {
        $template = ReportTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'format' => 'sometimes|string|in:docx,pdf',
            'async' => 'sometimes|boolean',
        ]);

        // Override format if specified
        if (isset($validated['format'])) {
            $template->format = $validated['format'];
        }

        $searchProfile = isset($validated['search_profile_id'])
            ? SearchProfile::find($validated['search_profile_id'])
            : null;

        $async = $validated['async'] ?? false;

        if ($async) {
            $reportJob = ReportJob::create([
                'report_template_id' => $template->id,
                'search_profile_id' => $searchProfile?->id ?? $template->search_profile_id,
                'format' => $template->format,
                'last_status' => 'pending',
                'user_id' => $request->user()?->id,
            ]);

            ExecuteReportJob::dispatch($reportJob->id);

            return response()->json([
                'message' => 'Report-Job in Warteschlange eingereiht',
                'data' => $reportJob,
            ], 202);
        }

        $result = $this->reportService->execute($template, $searchProfile);

        return response()->download(
            $result['path'],
            basename($result['path']),
        )->deleteFileAfterSend(false);
    }

    /**
     * POST /api/v1/report-templates/{id}/preview — Preview with limited products.
     */
    public function preview(Request $request, string $id)
    {
        $template = ReportTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'format' => 'sometimes|string|in:docx,pdf',
            'limit' => 'sometimes|integer|min:1|max:10',
        ]);

        if (isset($validated['format'])) {
            $template->format = $validated['format'];
        }

        $searchProfile = isset($validated['search_profile_id'])
            ? SearchProfile::find($validated['search_profile_id'])
            : null;

        $result = $this->reportService->preview($template, $searchProfile, $validated['limit'] ?? 5);

        return response()->download(
            $result['path'],
            basename($result['path']),
        )->deleteFileAfterSend(true);
    }

    /**
     * GET /api/v1/report-jobs/{id} — Job status.
     */
    public function jobStatus(Request $request, string $id): JsonResponse
    {
        $job = ReportJob::findOrFail($id);

        return response()->json(['data' => $job->load('reportTemplate')]);
    }

    /**
     * GET /api/v1/report-jobs/{id}/download — Download generated file.
     */
    public function jobDownload(Request $request, string $id)
    {
        $job = ReportJob::findOrFail($id);

        if (!$job->last_output_path || !file_exists($job->last_output_path)) {
            return response()->json([
                'error' => 'Keine Report-Datei vorhanden. Bitte zuerst den Report ausführen.',
            ], 404);
        }

        return response()->download(
            $job->last_output_path,
            basename($job->last_output_path),
        );
    }

    private function authorizeAccess(Request $request, ReportTemplate $template): void
    {
        $userId = $request->user()?->id;
        if (!$template->is_shared && $template->user_id && $userId !== $template->user_id) {
            abort(403, 'Kein Zugriff auf dieses Report-Template.');
        }
    }
}
