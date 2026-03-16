<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\AssetCatalogResource;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\MediaAttributeValue;
use App\Support\KoelnerPhonetik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AssetCatalogController extends BaseController
{
    /**
     * GET /api/v1/asset-catalog/assets
     *
     * Paginated list of media assets with optional folder/search/usage filtering.
     * Enhanced search with match_sources, phonetic matching, and attribute search.
     */
    public function assets(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'de');
        $perPage = min(max(1, (int) $request->query('per_page', '24')), 100);
        $sortField = $request->query('sort', 'created_at');
        $sortOrder = $request->query('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $search = $request->query('search');
        $folderId = $request->query('folder');
        $usagePurpose = $request->query('usage_purpose');
        $mediaType = $request->query('media_type');

        $query = Media::query()->with([
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.dictionaryEntry',
            'attributeValues.unit',
            'assetFolder',
        ]);

        $isSearchActive = $search && trim($search) !== '';

        // Folder filter (including descendants)
        if ($folderId) {
            $node = HierarchyNode::find($folderId);
            if ($node) {
                $descendantPrefix = $node->path === '/'
                    ? "/{$node->id}/"
                    : "{$node->path}{$node->id}/";

                $descendantIds = HierarchyNode::where('path', 'like', $descendantPrefix . '%')
                    ->pluck('id')
                    ->toArray();
                $descendantIds[] = $node->id;

                $query->whereIn('asset_folder_id', $descendantIds);
            }
        }

        // Usage purpose filter
        if ($usagePurpose && in_array($usagePurpose, ['print', 'web', 'both'])) {
            $query->where(function ($q) use ($usagePurpose) {
                $q->where('usage_purpose', $usagePurpose)
                    ->orWhere('usage_purpose', 'both');
            });
        }

        // Media type filter
        if ($mediaType) {
            $query->where('media_type', $mediaType);
        }

        // Enhanced search
        if ($isSearchActive) {
            $term = trim($search);
            $likeTerm = '%' . $term . '%';

            // Search in media fields + EAV attribute values
            $mediaIdsFromAttributes = MediaAttributeValue::where('value_string', 'like', $likeTerm)
                ->pluck('media_id')
                ->unique()
                ->toArray();

            // Phonetic matching (Kölner Phonetik) — pre-query matching media IDs
            $phoneticTerm = KoelnerPhonetik::encode($term);
            $mediaIdsFromPhonetic = [];
            if ($phoneticTerm !== '') {
                $mediaIdsFromPhonetic = Media::select('id', 'file_name', 'title_de')
                    ->where(function ($pq) use ($likeTerm) {
                        $pq->where('file_name', 'like', '%')
                            ->orWhere('title_de', 'like', '%');
                    })
                    ->get()
                    ->filter(function ($m) use ($phoneticTerm) {
                        if ($m->file_name) {
                            $fp = KoelnerPhonetik::encode($m->file_name);
                            if ($fp !== '' && str_contains($fp, $phoneticTerm)) return true;
                        }
                        if ($m->title_de) {
                            $tp = KoelnerPhonetik::encode($m->title_de);
                            if ($tp !== '' && str_contains($tp, $phoneticTerm)) return true;
                        }
                        return false;
                    })
                    ->pluck('id')
                    ->toArray();
            }

            $query->where(function ($q) use ($likeTerm, $mediaIdsFromAttributes, $mediaIdsFromPhonetic) {
                $q->where('file_name', 'like', $likeTerm)
                    ->orWhere('title_de', 'like', $likeTerm)
                    ->orWhere('title_en', 'like', $likeTerm)
                    ->orWhere('description_de', 'like', $likeTerm)
                    ->orWhere('description_en', 'like', $likeTerm);

                if (!empty($mediaIdsFromAttributes)) {
                    $q->orWhereIn('id', $mediaIdsFromAttributes);
                }

                if (!empty($mediaIdsFromPhonetic)) {
                    $q->orWhereIn('id', $mediaIdsFromPhonetic);
                }
            });
        }

        // Sorting
        $sortColumn = match ($sortField) {
            'name' => $lang === 'en' ? 'title_en' : 'title_de',
            'file_size' => 'file_size',
            'file_name' => 'file_name',
            default => 'created_at',
        };
        $query->orderBy($sortColumn, $sortOrder);

        $paginated = $query->paginate($perPage);

        // Compute match_sources for search results
        if ($isSearchActive) {
            $term = mb_strtolower(trim($search));
            $phoneticTerm = KoelnerPhonetik::encode(trim($search));

            foreach ($paginated->items() as $item) {
                $sources = [];

                if (str_contains(mb_strtolower($item->file_name ?? ''), $term)) {
                    $sources[] = ['type' => 'filename', 'label' => $lang === 'en' ? 'Filename' : 'Dateiname'];
                }

                $titleDe = mb_strtolower($item->title_de ?? '');
                $titleEn = mb_strtolower($item->title_en ?? '');
                if (str_contains($titleDe, $term) || str_contains($titleEn, $term)) {
                    $sources[] = ['type' => 'title', 'label' => $lang === 'en' ? 'Title' : 'Titel'];
                }

                $descDe = mb_strtolower($item->description_de ?? '');
                $descEn = mb_strtolower($item->description_en ?? '');
                if (str_contains($descDe, $term) || str_contains($descEn, $term)) {
                    $sources[] = ['type' => 'description', 'label' => $lang === 'en' ? 'Description' : 'Beschreibung'];
                }

                // Check attribute matches
                if ($item->relationLoaded('attributeValues')) {
                    foreach ($item->attributeValues as $attrValue) {
                        $attr = $attrValue->attribute;
                        if (!$attr) continue;
                        $valueStr = mb_strtolower($attrValue->value_string ?? '');
                        if ($valueStr !== '' && str_contains($valueStr, $term)) {
                            $attrName = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;
                            $sources[] = ['type' => 'attribute', 'label' => $attrName];
                            break; // One attribute match is enough
                        }
                    }
                }

                // Phonetic match (only if no other matches found)
                if (empty($sources) && $phoneticTerm !== '') {
                    $isPhoneticMatch = false;
                    if ($item->file_name) {
                        $filePhonetic = KoelnerPhonetik::encode($item->file_name);
                        if ($filePhonetic !== '' && str_contains($filePhonetic, $phoneticTerm)) {
                            $isPhoneticMatch = true;
                        }
                    }
                    if (!$isPhoneticMatch && $item->title_de) {
                        $titlePhonetic = KoelnerPhonetik::encode($item->title_de);
                        if ($titlePhonetic !== '' && str_contains($titlePhonetic, $phoneticTerm)) {
                            $isPhoneticMatch = true;
                        }
                    }
                    if ($isPhoneticMatch) {
                        $sources[] = ['type' => 'phonetic', 'label' => $lang === 'en' ? 'Sounds similar' : 'Ähnlich klingend'];
                    }
                }

                // Fallback
                if (empty($sources)) {
                    $sources[] = ['type' => 'filename', 'label' => $lang === 'en' ? 'Filename' : 'Dateiname'];
                }

                $item->match_sources = $sources;
            }
        }

        return response()->json([
            'data' => AssetCatalogResource::collection($paginated->items())
                ->additional(['lang' => $lang])
                ->resolve(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/asset-catalog/assets/{media}
     *
     * Single asset detail with all metadata.
     */
    public function asset(Request $request, Media $medium): JsonResponse
    {
        $lang = $request->query('lang', 'de');

        $medium->load([
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.dictionaryEntry',
            'attributeValues.unit',
            'assetFolder',
        ]);

        // Build folder breadcrumb
        $breadcrumb = [];
        if ($medium->assetFolder) {
            $node = $medium->assetFolder;
            $ancestors = HierarchyNode::ancestorsOf($node->path)
                ->orderBy('depth')
                ->get();

            foreach ($ancestors as $ancestor) {
                $breadcrumb[] = [
                    'id' => $ancestor->id,
                    'name' => $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de,
                ];
            }
            $breadcrumb[] = [
                'id' => $node->id,
                'name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
            ];
        }

        return response()->json([
            'data' => (new AssetCatalogResource($medium))
                ->additional(['lang' => $lang, 'breadcrumb' => $breadcrumb])
                ->resolve(),
        ]);
    }

    /**
     * GET /api/v1/asset-catalog/assets/{media}/products
     *
     * Products that use this asset, paginated.
     */
    public function assetProducts(Request $request, Media $medium): JsonResponse
    {
        $lang = $request->query('lang', 'de');
        $perPage = min(max(1, (int) $request->query('per_page', '10')), 50);

        $query = $medium->products()
            ->where('products.status', 'active')
            ->where('products.product_type_ref', 'product');

        // Try to join search index for display data
        $hasSearchIndex = DB::getSchemaBuilder()->hasTable('products_search_index');
        if ($hasSearchIndex) {
            $query->leftJoin('products_search_index', 'products_search_index.product_id', '=', 'products.id')
                ->select(
                    'products.id',
                    'products.sku',
                    'products.ean',
                    'products_search_index.name_de',
                    'products_search_index.name_en',
                    'products_search_index.primary_image',
                );
        } else {
            $query->select('products.id', 'products.sku', 'products.ean');
        }

        $paginated = $query->paginate($perPage);

        $data = collect($paginated->items())->map(function ($product) use ($lang, $hasSearchIndex) {
            $name = $lang === 'en' && ($product->name_en ?? null)
                ? $product->name_en
                : ($product->name_de ?? $product->sku);

            $imageUrl = null;
            if ($hasSearchIndex && $product->primary_image) {
                $imageUrl = url('api/v1/media/thumb/' . $product->primary_image . '?w=80&h=80');
            }

            return [
                'id' => $product->id,
                'name' => $name,
                'sku' => $product->sku,
                'ean' => $product->ean,
                'image_url' => $imageUrl,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/asset-catalog/folders
     *
     * Folder tree from asset hierarchy.
     */
    public function folders(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'de');

        $hierarchy = Hierarchy::where('hierarchy_type', 'asset')->first();

        if (!$hierarchy) {
            return response()->json([
                'data' => [
                    'hierarchy_id' => null,
                    'hierarchy_name' => null,
                    'nodes' => [],
                ],
            ]);
        }

        $allNodes = $hierarchy->nodes()
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->get();

        // Count media per folder (including descendants)
        $nodeIds = $allNodes->pluck('id')->toArray();
        $directCounts = Media::whereIn('asset_folder_id', $nodeIds)
            ->groupBy('asset_folder_id')
            ->selectRaw('asset_folder_id, COUNT(*) as cnt')
            ->pluck('cnt', 'asset_folder_id')
            ->toArray();

        $counts = [];
        foreach ($allNodes as $node) {
            $counts[$node->id] = $directCounts[$node->id] ?? 0;
        }

        // Roll up counts from children to parents
        $sortedNodes = $allNodes->sortByDesc('depth');
        foreach ($sortedNodes as $node) {
            if ($node->parent_node_id && isset($counts[$node->parent_node_id])) {
                $counts[$node->parent_node_id] += $counts[$node->id];
            }
        }

        // Build nested tree
        $rootNodes = $allNodes->whereNull('parent_node_id');
        $nodesByParent = $allNodes->groupBy('parent_node_id');

        $buildTree = function ($nodes) use (&$buildTree, $nodesByParent, $counts, $lang) {
            return $nodes->map(function ($node) use (&$buildTree, $nodesByParent, $counts, $lang) {
                $children = $nodesByParent->get($node->id, collect());
                return [
                    'id' => $node->id,
                    'name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
                    'asset_count' => $counts[$node->id] ?? 0,
                    'children' => $buildTree($children)->values()->toArray(),
                ];
            })->values();
        };

        return response()->json([
            'data' => [
                'hierarchy_id' => $hierarchy->id,
                'hierarchy_name' => $lang === 'en' && $hierarchy->name_en ? $hierarchy->name_en : $hierarchy->name_de,
                'nodes' => $buildTree($rootNodes)->toArray(),
            ],
        ]);
    }

    /**
     * POST /api/v1/asset-catalog/download
     *
     * ZIP download of selected assets.
     * Body: { "media_ids": ["uuid1", "uuid2", ...] }
     */
    public function download(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate([
            'media_ids' => 'required|array|min:1|max:100',
            'media_ids.*' => 'uuid|exists:media,id',
        ]);

        $mediaItems = Media::whereIn('id', $request->input('media_ids'))->get();

        if ($mediaItems->isEmpty()) {
            return response()->json(['message' => 'Keine Assets gefunden.'], 404);
        }

        $disk = Storage::disk('public');

        return response()->streamDownload(function () use ($mediaItems, $disk) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'pim_assets_');

            // Ensure cleanup even on stream interruption
            register_shutdown_function(function () use ($tmpFile) {
                if (file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            });

            $zip = new ZipArchive();
            if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return;
            }

            foreach ($mediaItems as $media) {
                $filePath = $disk->path($media->file_path);
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $media->file_name);
                }
            }

            $zip->close();

            readfile($tmpFile);
            @unlink($tmpFile);
        }, 'pim-assets-' . date('Y-m-d-His') . '.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
