<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Preview\ProductCompletenessService;
use App\Services\Preview\ProductPreviewService;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Services\ProductVersioningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_INCLUDES = [
        'productType', 'attributeValues', 'variants', 'media',
        'prices', 'relations', 'parentProduct', 'masterHierarchyNode', 'masterHierarchyNode.hierarchy',
        'manufacturer', 'workflow', 'currentWorkflowStatus', 'workflowAssignee', 'workflowTeam', 'projects',
    ];

    private const ALLOWED_FILTERS = [
        'status', 'product_type_id', 'product_type_ref',
        'master_hierarchy_node_id', 'manufacturer_id', 'current_workflow_status_id',
        'project_id',
    ];

    // Felder, die per Präfix-Suche (LIKE 'wert%') statt Exakt-Match gefiltert werden,
    // z.B. für das Quick-Lookup im Produkte-Menü.
    private const ALLOWED_PREFIX_FILTERS = ['sku', 'name', 'ean'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $languages = $this->getRequestedLanguages($request);

        $query = Product::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        // Optionally join primary_image from search index for thumbnail display
        if ($request->boolean('include_thumbnail')) {
            $query->leftJoin('products_search_index', 'products.id', '=', 'products_search_index.product_id')
                ->addSelect('products.*', 'products_search_index.primary_image');
        }

        // If attributeValues are included, filter by language
        if (in_array('attributeValues', $this->parseIncludes($request, self::ALLOWED_INCLUDES))) {
            $this->constrainAttributeValuesForLanguages($query, $languages);
        }

        $rawFilters = $request->query('filter', []);

        $this->applyPrefixFilters($query, $rawFilters, self::ALLOWED_PREFIX_FILTERS, 'products');

        $filters = array_intersect_key(
            $rawFilters,
            array_flip(self::ALLOWED_FILTERS)
        );

        // By default, exclude variants from the main product listing.
        // Virtuelle Produkte ("Klammer") sind eigenständige Katalogprodukte
        // und bleiben daher in der Hauptliste sichtbar.
        if (!isset($filters['product_type_ref'])) {
            $query->whereIn('products.product_type_ref', ['product', 'virtual']);
        }

        // Handle project_id filter via pivot table
        if (!empty($filters['project_id'])) {
            $projectIds = str_contains($filters['project_id'], ',')
                ? explode(',', $filters['project_id'])
                : [$filters['project_id']];
            $query->whereHas('projects', fn ($q) => $q->whereIn('projects.id', $projectIds));
            unset($filters['project_id']);
        }

        $this->applyFilters($query, $filters);
        $this->applySearch($query, $request, ['products.name', 'products.sku', 'products.ean']);

        // Sorting durch Relationsspalten (z.B. Hierarchie-Knoten) erfordert einen Join,
        // da applySorting() nur direkte Spalten der products-Tabelle sortieren kann.
        if ($request->query('sort') === 'master_hierarchy_node.name_de') {
            $order = strtolower($request->query('order', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->leftJoin('hierarchy_nodes', 'products.master_hierarchy_node_id', '=', 'hierarchy_nodes.id')
                ->orderBy('hierarchy_nodes.name_de', $order)
                ->addSelect('products.*');
        } else {
            $this->applySorting($query, $request, 'created_at', 'desc');
        }

        $paginated = $query->paginate($this->getPerPage($request));

        // Optionally load attribute column values (for dynamic table columns)
        $attributeColumns = $request->input('attribute_columns', []);
        if (!empty($attributeColumns) && is_array($attributeColumns)) {
            $language = $request->input('language', 'de');
            $productIds = collect($paginated->items())->pluck('id');
            $attrValues = ProductAttributeValue::whereIn('product_id', $productIds)
                ->whereIn('attribute_id', $attributeColumns)
                ->where(fn ($q) => $q->where('language', $language)->orWhereNull('language'))
                ->with('valueListEntry')
                ->get()
                ->groupBy('product_id');

            foreach ($paginated->items() as $product) {
                $attrs = [];
                $productAttrValues = $attrValues->get($product->id, collect());
                foreach ($attributeColumns as $attrId) {
                    $av = $productAttrValues->firstWhere('attribute_id', $attrId);
                    if (!$av) {
                        $attrs[$attrId] = null;
                    } elseif ($av->value_selection_id && $av->valueListEntry) {
                        $attrs[$attrId] = $av->valueListEntry->display_value_de ?? $av->valueListEntry->code ?? '';
                    } elseif ($av->value_flag !== null) {
                        $attrs[$attrId] = $av->value_flag ? 'Ja' : 'Nein';
                    } elseif ($av->value_date !== null) {
                        $attrs[$attrId] = $av->value_date;
                    } elseif ($av->value_number !== null) {
                        $attrs[$attrId] = $av->value_number;
                    } else {
                        $attrs[$attrId] = $av->value_string ?? '';
                    }
                }
                $product->setAttribute('attributes', $attrs);
            }
        }

        return ProductResource::collection($paginated);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        // Auto-assign workflow from ProductType
        if (!empty($data['product_type_id'])) {
            $productType = \App\Models\ProductType::find($data['product_type_id']);
            if ($productType?->workflow_id) {
                $data['workflow_id'] = $productType->workflow_id;
            }
        }

        $product = Product::create($data);

        try {
            event(new \App\Events\ProductCreated($product));
        } catch (\Throwable $e) {
            Log::warning('ProductCreated event failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        return (new ProductResource($product->load('productType')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /products/{product}/duplicate
     *
     * Deep-copy a product with selectable parts.
     */
    public function duplicate(Request $request, Product $product): JsonResponse
    {
        $this->authorize('create', Product::class);

        $includeAttributes = $request->boolean('include_attributes', true);
        $includePrices = $request->boolean('include_prices', true);
        $includeMedia = $request->boolean('include_media', true);
        $includeRelations = $request->boolean('include_relations', true);

        $newProduct = null;

        DB::transaction(function () use (
            $product, $request, $includeAttributes, $includePrices, $includeMedia, $includeRelations, &$newProduct
        ) {
            // Generate unique SKU
            $baseSku = $product->sku . '-copy';
            $sku = $baseSku;
            $suffix = 1;
            while (Product::where('sku', $sku)->exists()) {
                $sku = $baseSku . '-' . $suffix;
                $suffix++;
            }

            // Create new product
            $newProduct = Product::create([
                'sku' => $sku,
                'name' => $product->name . ' (Kopie)',
                'ean' => $product->ean,
                'status' => 'draft',
                'product_type_id' => $product->product_type_id,
                'product_type_ref' => 'product',
                'master_hierarchy_node_id' => $product->master_hierarchy_node_id,
                'manufacturer_id' => $product->manufacturer_id,
                'created_by' => $request->user()?->id,
            ]);

            // Copy attribute values
            if ($includeAttributes) {
                foreach ($product->attributeValues as $av) {
                    $newAv = $av->replicate();
                    $newAv->product_id = $newProduct->id;
                    $newAv->save();
                }
            }

            // Copy prices
            if ($includePrices) {
                foreach ($product->prices as $price) {
                    $newPrice = $price->replicate();
                    $newPrice->product_id = $newProduct->id;
                    $newPrice->save();
                }
            }

            // Copy media assignments
            if ($includeMedia) {
                foreach ($product->mediaAssignments as $ma) {
                    $newMa = $ma->replicate();
                    $newMa->product_id = $newProduct->id;
                    $newMa->save();
                }
            }

            // Copy outgoing relations
            if ($includeRelations) {
                foreach ($product->outgoingRelations as $rel) {
                    $newRel = $rel->replicate();
                    $newRel->source_product_id = $newProduct->id;
                    $newRel->save();
                }
            }
        });

        try {
            event(new \App\Events\ProductCreated($newProduct));
        } catch (\Throwable $e) {
            Log::warning('ProductCreated event failed for duplicate', ['product_id' => $newProduct->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Product duplicated successfully.',
            'product' => new ProductResource($newProduct->load('productType')),
        ], 201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $languages = $this->getRequestedLanguages($request);
        $includes = $this->parseIncludes($request, self::ALLOWED_INCLUDES);

        // Build eager loading with language constraint for attribute values
        $eagerLoads = [];
        foreach ($includes as $include) {
            if ($include === 'attributeValues') {
                $eagerLoads['attributeValues'] = function ($q) use ($languages) {
                    $q->where(function ($sub) use ($languages) {
                        $sub->whereNull('language')
                            ->orWhereIn('language', $languages);
                    });
                };
            } else {
                $eagerLoads[] = $include;
            }
        }

        $product->load($eagerLoads);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        // Auto-create version snapshot before applying changes
        try {
            app(ProductVersioningService::class)->createVersion(
                $product,
                null,
                $request->user()?->id,
            );
        } catch (\Throwable) {
            // Don't break the update if versioning fails
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()?->id;

        // Workflow status transition handling (new FK-based system)
        $oldStatusId = $product->current_workflow_status_id;
        $newStatusId = $data['current_workflow_status_id'] ?? $oldStatusId;

        if ($newStatusId !== $oldStatusId && $newStatusId !== null) {
            // Validate that the transition is allowed
            if ($product->workflow_id) {
                if ($oldStatusId) {
                    // Must be a defined transition
                    $transitionExists = WorkflowTransition::where('workflow_id', $product->workflow_id)
                        ->where('from_status_id', $oldStatusId)
                        ->where('to_status_id', $newStatusId)
                        ->exists();

                    if (!$transitionExists) {
                        return response()->json([
                            'message' => 'Dieser Workflow-Übergang ist nicht erlaubt.',
                            'errors' => ['current_workflow_status_id' => ['Der Übergang von dem aktuellen Status zu dem gewählten Status ist nicht definiert.']],
                        ], 422);
                    }
                } else {
                    // No current status → only the workflow's start_status is allowed
                    $workflow = \App\Models\Workflow::find($product->workflow_id);
                    if ($workflow && $workflow->start_status_id !== $newStatusId) {
                        return response()->json([
                            'message' => 'Dieser Workflow-Übergang ist nicht erlaubt.',
                            'errors' => ['current_workflow_status_id' => ['Der Workflow muss mit dem Start-Status beginnen.']],
                        ], 422);
                    }
                }
            }

            // Load the new status to get its name for the task
            $newStatus = \App\Models\WorkflowStatus::find($newStatusId);

            // Auto-publish: when reaching the workflow's end status on a draft product
            if ($product->workflow_id && $product->status === 'draft') {
                $workflow = \App\Models\Workflow::find($product->workflow_id);
                if ($workflow && $workflow->end_status_id === $newStatusId) {
                    $data['status'] = 'active';
                    $data['current_workflow_status_id'] = null;
                    $data['workflow_assignee_id'] = null;
                    $data['workflow_team_id'] = null;
                }
            }

            // Auto-create workflow task for the transition
            WorkflowTask::create([
                'product_id' => $product->id,
                'title' => $newStatus?->name ?? 'Workflow-Übergang',
                'status' => 'open',
                'workflow_status_id' => $newStatusId,
                'assigned_to' => $data['workflow_assignee_id'] ?? $product->workflow_assignee_id,
                'team_id' => $data['workflow_team_id'] ?? $product->workflow_team_id,
                'created_by' => $request->user()?->id,
            ]);
        }

        // Close open tasks when workflow is cleared
        if ($newStatusId !== $oldStatusId && $newStatusId === null) {
            WorkflowTask::where('product_id', $product->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->update(['status' => 'closed', 'closed_at' => now()]);
        }

        // Sync project assignments
        if (array_key_exists('project_ids', $data)) {
            $product->projects()->sync($data['project_ids'] ?? []);
            unset($data['project_ids']);
        }

        $product->update($data);

        try {
            event(new \App\Events\ProductUpdated($product));
        } catch (\Throwable $e) {
            Log::warning('ProductUpdated event failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        return new ProductResource($product->fresh());
    }

    public function dependencies(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->dependenciesResponse($product);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $snapshot = $product->only(['id', 'sku', 'name', 'ean', 'status', 'product_type_id', 'product_type_ref']);

        $response = $this->destroyWithConstraintCheck($request, $product);

        // Only dispatch event if deletion was successful (204)
        if ($response->getStatusCode() === 204) {
            try {
                event(new \App\Events\ProductDeleted($product->id, $snapshot));
            } catch (\Throwable $e) {
                Log::warning('ProductDeleted event failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            }
        }

        return $response;
    }

    /**
     * GET /products/compare?ids=uuid1,uuid2
     *
     * Compare 2 products across ALL attributes, highlighting differences.
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = explode(',', $request->query('ids'));
        if (count($ids) !== 2) {
            return response()->json(['message' => 'Exactly 2 product IDs required.'], 422);
        }

        $products = Product::with('productType')->whereIn('id', $ids)->get();
        if ($products->count() !== 2) {
            return response()->json(['message' => 'One or both products not found.'], 404);
        }

        $language = $this->getPrimaryLanguage($request);
        $productA = $products->firstWhere('id', $ids[0]);
        $productB = $products->firstWhere('id', $ids[1]);

        // Load ALL attribute values for both products
        $valsA = $productA->attributeValues()
            ->with('attribute')
            ->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            })
            ->get();

        $valsB = $productB->attributeValues()
            ->with('attribute')
            ->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            })
            ->get();

        // Build maps: attribute_id -> display value
        $mapA = [];
        foreach ($valsA as $v) {
            $value = $v->value_string ?? $v->value_number ?? $v->value_date ?? $v->value_flag ?? $v->value_selection_id;
            $mapA[$v->attribute_id] = [
                'value' => $value,
                'attribute_name' => $v->attribute?->name_de ?? $v->attribute?->technical_name ?? 'Unknown',
                'technical_name' => $v->attribute?->technical_name ?? '',
                'data_type' => $v->attribute?->data_type ?? '',
                'language' => $v->language,
            ];
        }

        $mapB = [];
        foreach ($valsB as $v) {
            $value = $v->value_string ?? $v->value_number ?? $v->value_date ?? $v->value_flag ?? $v->value_selection_id;
            $mapB[$v->attribute_id] = [
                'value' => $value,
                'attribute_name' => $v->attribute?->name_de ?? $v->attribute?->technical_name ?? 'Unknown',
                'technical_name' => $v->attribute?->technical_name ?? '',
                'data_type' => $v->attribute?->data_type ?? '',
                'language' => $v->language,
            ];
        }

        // Merge all attribute IDs
        $allAttrIds = array_unique(array_merge(array_keys($mapA), array_keys($mapB)));

        // Build base field comparisons
        $rows = [];

        // Compare base fields first
        $baseFields = [
            ['field' => 'sku', 'label' => 'SKU'],
            ['field' => 'name', 'label' => 'Name'],
            ['field' => 'ean', 'label' => 'EAN'],
            ['field' => 'status', 'label' => 'Status'],
            ['field' => 'product_type_ref', 'label' => 'Typ'],
        ];

        foreach ($baseFields as $bf) {
            $valA = $productA->{$bf['field']};
            $valB = $productB->{$bf['field']};
            $rows[] = [
                'attribute_name' => $bf['label'],
                'technical_name' => $bf['field'],
                'data_type' => 'base',
                'value_a' => $valA,
                'value_b' => $valB,
                'is_different' => (string) $valA !== (string) $valB,
            ];
        }

        // Compare attribute values
        foreach ($allAttrIds as $attrId) {
            $a = $mapA[$attrId] ?? null;
            $b = $mapB[$attrId] ?? null;
            $name = $a['attribute_name'] ?? $b['attribute_name'] ?? 'Unknown';
            $techName = $a['technical_name'] ?? $b['technical_name'] ?? '';
            $dataType = $a['data_type'] ?? $b['data_type'] ?? '';
            $valA = $a['value'] ?? null;
            $valB = $b['value'] ?? null;

            $rows[] = [
                'attribute_id' => $attrId,
                'attribute_name' => $name,
                'technical_name' => $techName,
                'data_type' => $dataType,
                'value_a' => $valA,
                'value_b' => $valB,
                'is_different' => (string) $valA !== (string) $valB,
            ];
        }

        return response()->json([
            'data' => [
                'product_a' => [
                    'id' => $productA->id,
                    'sku' => $productA->sku,
                    'name' => $productA->name,
                ],
                'product_b' => [
                    'id' => $productB->id,
                    'sku' => $productB->sku,
                    'name' => $productB->name,
                ],
                'rows' => $rows,
                'total_differences' => collect($rows)->where('is_different', true)->count(),
                'total_attributes' => count($rows),
            ],
        ]);
    }

    /**
     * GET /products/{product}/preview
     *
     * Generic product preview — all data in structured sections.
     */
    public function preview(Request $request, Product $product, ProductPreviewService $previewService): JsonResponse
    {
        $this->authorize('view', $product);

        $lang = $this->getPrimaryLanguage($request);
        // Wenn ?lang= explizit gesetzt → nur diese Sprache anzeigen, sonst alle
        $filterLang = $request->query('lang') ? $lang : null;
        $data = $previewService->buildPreviewData($product, $lang, $filterLang);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /products/{product}/preview/export.xlsx
     *
     * Export product preview as Excel file (single sheet, sections stacked).
     */
    public function previewExportExcel(Request $request, Product $product, ProductPreviewService $previewService): StreamedResponse
    {
        $this->authorize('view', $product);

        $lang = $this->getPrimaryLanguage($request);
        $data = $previewService->buildPreviewData($product, $lang);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($lang === 'en' ? 'Product Preview' : 'Produkt-Vorschau');

        $row = 1;

        // Styling constants
        $sectionHeaderStyle = [
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $fieldLabelStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
        ];

        // --- Stammdaten ---
        $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Master Data' : 'Stammdaten');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
        $row++;

        $stamm = $data['stammdaten'];
        $stammFields = [
            ['SKU', $stamm['sku']],
            ['EAN', $stamm['ean']],
            ['Name', $stamm['name']],
            ['Status', $stamm['status']],
            [$lang === 'en' ? 'Product Type' : 'Produkttyp', $stamm['product_type']['name'] ?? '-'],
            [$lang === 'en' ? 'Category' : 'Kategorie', implode(' > ', array_column($stamm['category_breadcrumb'], 'name'))],
            [$lang === 'en' ? 'Created' : 'Erstellt', $stamm['created_at']],
            [$lang === 'en' ? 'Updated' : 'Aktualisiert', $stamm['updated_at']],
        ];

        foreach ($stammFields as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->applyFromArray($fieldLabelStyle);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        $row++; // spacer

        // --- Attribute Sections ---
        foreach ($data['attribute_sections'] as $section) {
            $sheet->setCellValue("A{$row}", $section['section_name']);
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
            $row++;

            // Column headers
            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Attribute' : 'Attribut');
            $sheet->setCellValue("B{$row}", $lang === 'en' ? 'Value' : 'Wert');
            $sheet->setCellValue("C{$row}", $lang === 'en' ? 'Unit' : 'Einheit');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($fieldLabelStyle);
            $row++;

            foreach ($section['attributes'] as $attr) {
                $sheet->setCellValue("A{$row}", $attr['label']);
                $sheet->setCellValue("B{$row}", $attr['display_value'] ?? '-');
                $sheet->setCellValue("C{$row}", $attr['unit'] ?? '');
                $row++;
            }

            $row++; // spacer
        }

        // --- Relations ---
        if (!empty($data['relations'])) {
            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Relations' : 'Beziehungen');
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Type' : 'Typ');
            $sheet->setCellValue("B{$row}", $lang === 'en' ? 'Target Product' : 'Zielprodukt');
            $sheet->setCellValue("C{$row}", 'SKU');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($fieldLabelStyle);
            $row++;

            foreach ($data['relations'] as $rel) {
                $sheet->setCellValue("A{$row}", $rel['relation_type'] ?? '-');
                $sheet->setCellValue("B{$row}", $rel['target_product']['name'] ?? '-');
                $sheet->setCellValue("C{$row}", $rel['target_product']['sku'] ?? '-');
                $row++;
            }

            $row++;
        }

        // --- Prices ---
        if (!empty($data['prices'])) {
            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Prices' : 'Preise');
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Price Type' : 'Preistyp');
            $sheet->setCellValue("B{$row}", $lang === 'en' ? 'Amount' : 'Betrag');
            $sheet->setCellValue("C{$row}", $lang === 'en' ? 'Currency' : 'Währung');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($fieldLabelStyle);
            $row++;

            foreach ($data['prices'] as $price) {
                $sheet->setCellValue("A{$row}", $price['price_type'] ?? '-');
                $sheet->setCellValue("B{$row}", $price['amount']);
                $sheet->setCellValue("C{$row}", $price['currency']);
                $row++;
            }

            $row++;
        }

        // --- Variants ---
        if (!empty($data['variants'])) {
            $sheet->setCellValue("A{$row}", $lang === 'en' ? 'Variants' : 'Varianten');
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($sectionHeaderStyle);
            $row++;

            $sheet->setCellValue("A{$row}", 'SKU');
            $sheet->setCellValue("B{$row}", 'Name');
            $sheet->setCellValue("C{$row}", 'Status');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($fieldLabelStyle);
            $row++;

            foreach ($data['variants'] as $variant) {
                $sheet->setCellValue("A{$row}", $variant['sku'] ?? '-');
                $sheet->setCellValue("B{$row}", $variant['name'] ?? '-');
                $sheet->setCellValue("C{$row}", $variant['status'] ?? '-');
                $row++;
            }
        }

        // Auto-size columns
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'product-preview-' . ($stamm['sku'] ?? $product->id) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * GET /products/{product}/preview/export.pdf
     *
     * Export product preview as PDF.
     */
    public function previewExportPdf(Request $request, Product $product, ProductPreviewService $previewService): \Illuminate\Http\Response
    {
        $this->authorize('view', $product);

        $lang = $this->getPrimaryLanguage($request);
        $data = $previewService->buildPreviewData($product, $lang);

        $filename = 'product-preview-' . ($data['stammdaten']['sku'] ?? $product->id) . '.pdf';

        $pdf = Pdf::loadView('exports.product-preview', [
            'data' => $data,
            'lang' => $lang,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    /**
     * GET /products/{product}/completeness
     *
     * Detailed completeness analysis per section.
     * Includes SVG gauge chart for visual representation.
     */
    public function completeness(Request $request, Product $product, ProductCompletenessService $completenessService): JsonResponse
    {
        $this->authorize('view', $product);

        $lang = $this->getPrimaryLanguage($request);
        $data = $completenessService->calculateCompleteness($product, $lang);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /products/{product}/workflow-history
     *
     * Returns all workflow tasks (status transitions) for this product,
     * ordered chronologically.
     */
    public function workflowHistory(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $tasks = WorkflowTask::where('product_id', $product->id)
            ->with([
                'assignee:id,name',
                'creator:id,name',
                'workflowStatus:id,name,color',
                'team:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (WorkflowTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'note' => $task->note,
                'workflow_status' => $task->workflowStatus ? [
                    'id' => $task->workflowStatus->id,
                    'name' => $task->workflowStatus->name,
                    'color' => $task->workflowStatus->color,
                ] : null,
                'assignee' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->name,
                ] : null,
                'team' => $task->team ? [
                    'id' => $task->team->id,
                    'name' => $task->team->name,
                ] : null,
                'created_by' => $task->creator ? [
                    'id' => $task->creator->id,
                    'name' => $task->creator->name,
                ] : null,
                'created_at' => $task->created_at?->toIso8601String(),
                'closed_at' => $task->closed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $tasks]);
    }

    /**
     * GET /products/{product}/available-transitions
     *
     * Returns the allowed next workflow statuses based on the product's
     * current workflow and status.
     */
    public function availableTransitions(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        if (!$product->workflow_id) {
            return response()->json(['data' => []]);
        }

        $query = WorkflowTransition::where('workflow_id', $product->workflow_id)
            ->with('toStatus');

        if ($product->current_workflow_status_id) {
            $query->where('from_status_id', $product->current_workflow_status_id);
        } else {
            // No current status → show transitions from the workflow's start status
            $workflow = $product->workflow;
            if ($workflow?->start_status_id) {
                // Return the start status itself as the first available transition
                $startStatus = \App\Models\WorkflowStatus::find($workflow->start_status_id);
                return response()->json([
                    'data' => $startStatus ? [[
                        'to_status_id' => $startStatus->id,
                        'to_status' => [
                            'id' => $startStatus->id,
                            'name' => $startStatus->name,
                            'color' => $startStatus->color,
                        ],
                        'name' => $startStatus->name,
                    ]] : [],
                ]);
            }
            return response()->json(['data' => []]);
        }

        $transitions = $query->get()->map(fn (WorkflowTransition $t) => [
            'id' => $t->id,
            'to_status_id' => $t->to_status_id,
            'to_status' => $t->toStatus ? [
                'id' => $t->toStatus->id,
                'name' => $t->toStatus->name,
                'color' => $t->toStatus->color,
            ] : null,
            'name' => $t->name ?? $t->toStatus?->name,
        ]);

        return response()->json(['data' => $transitions]);
    }
}
