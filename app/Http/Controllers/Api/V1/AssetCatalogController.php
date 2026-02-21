<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\AssetCatalogResource;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AssetCatalogController extends BaseController
{
    /**
     * GET /api/v1/asset-catalog/assets
     *
     * Paginated list of media assets with optional folder/search/usage filtering.
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
            'attributeValues.unit',
            'assetFolder',
        ]);

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

        // Search
        if ($search && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('file_name', 'like', $term)
                    ->orWhere('title_de', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('description_de', 'like', $term);
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
            $zip = new ZipArchive();
            $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

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
