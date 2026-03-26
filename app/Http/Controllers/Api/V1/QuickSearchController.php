<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\ProductSearchIndex;
use App\Support\KoelnerPhonetik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Schnellsuche — Google-artige Suche über Produkte, Medien, Hierarchien und Attribute.
 *
 * Liefert gewichtete Ergebnisse mit Querverweisen (Kategorien, Produktanzahl, etc.)
 * und unterstützt Drill-Down-Filter für In-Page-Navigation.
 */
class QuickSearchController extends Controller
{
    /**
     * GET /api/v1/quick-search
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:500',
            'type' => 'nullable|string|in:products,media,hierarchies,attributes',
            'limit' => 'nullable|integer|min:1|max:50',
            'category_id' => 'nullable|string|uuid',
            'attribute_id' => 'nullable|string|uuid',
            'media_id' => 'nullable|string|uuid',
        ]);

        $query = trim($validated['q'] ?? '');
        $type = $validated['type'] ?? null;
        $limit = $validated['limit'] ?? 20;
        $categoryId = $validated['category_id'] ?? null;
        $attributeId = $validated['attribute_id'] ?? null;
        $mediaId = $validated['media_id'] ?? null;

        // Ohne Typ: alle 4 Typen mit kleinem Limit für Counts + Preview
        if (!$type) {
            $previewLimit = min($limit, 5);
            $products = $this->searchProducts($query, $previewLimit, $categoryId, $attributeId, $mediaId);
            $media = $this->searchMedia($query, $previewLimit);
            $hierarchies = $this->searchHierarchies($query, $previewLimit, $attributeId);
            $attributes = $this->searchAttributes($query, $previewLimit);

            return response()->json([
                'query' => $query,
                'counts' => [
                    'products' => $products['total'],
                    'media' => $media['total'],
                    'hierarchies' => $hierarchies['total'],
                    'attributes' => $attributes['total'],
                ],
                'results' => [
                    'products' => $products['items'],
                    'media' => $media['items'],
                    'hierarchies' => $hierarchies['items'],
                    'attributes' => $attributes['items'],
                ],
            ]);
        }

        // Mit Typ: nur diesen Typ mit vollem Limit
        $result = match ($type) {
            'products' => $this->searchProducts($query, $limit, $categoryId, $attributeId, $mediaId),
            'media' => $this->searchMedia($query, $limit),
            'hierarchies' => $this->searchHierarchies($query, $limit, $attributeId),
            'attributes' => $this->searchAttributes($query, $limit),
        };

        return response()->json([
            'query' => $query,
            'counts' => [$type => $result['total']],
            'results' => $result['items'],
        ]);
    }

    // ─── Produkte ────────────────────────────────────────

    private function searchProducts(string $query, int $limit, ?string $categoryId, ?string $attributeId, ?string $mediaId): array
    {
        $builder = ProductSearchIndex::query()
            ->join('products', 'products.id', '=', 'products_search_index.product_id')
            ->where('products.status', 'active')
            ->where('products.product_type_ref', 'product');

        // Drill-Down-Filter
        if ($categoryId) {
            $node = HierarchyNode::find($categoryId);
            if ($node) {
                $descendantIds = HierarchyNode::where('hierarchy_id', $node->hierarchy_id)
                    ->where('path', 'like', $node->path . '%')
                    ->pluck('id');
                $builder->whereIn('products.master_hierarchy_node_id', $descendantIds);
            }
        }
        if ($attributeId) {
            $builder->whereIn('products.id', function ($sub) use ($attributeId) {
                $sub->select('product_id')
                    ->from('product_attribute_values')
                    ->where('attribute_id', $attributeId)
                    ->distinct();
            });
        }
        if ($mediaId) {
            $builder->whereIn('products.id', function ($sub) use ($mediaId) {
                $sub->select('product_id')
                    ->from('product_media_assignments')
                    ->where('media_id', $mediaId)
                    ->distinct();
            });
        }

        $hasQuery = $query !== '';

        if ($hasQuery) {
            $this->applyProductSearch($builder, $query);
        }

        // Select-Spalten
        $builder->select([
            'products.id',
            'products_search_index.sku',
            'products_search_index.name_de',
            'products_search_index.name_en',
            'products_search_index.primary_image',
            'products_search_index.hierarchy_path',
            'products.master_hierarchy_node_id',
        ]);

        // Relevanz-Score für Sortierung
        if ($hasQuery && DB::getDriverName() === 'mysql') {
            $quoted = DB::getPdo()->quote($query . '*');
            $builder->addSelect(DB::raw(
                "(MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST({$quoted} IN BOOLEAN MODE)) * 10 "
                . "+ IF(products_search_index.searchable_text IS NOT NULL, (MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST({$quoted} IN BOOLEAN MODE)) * 3, 0) "
                . 'as relevance_score'
            ));
            $builder->orderByDesc('relevance_score');
        } else {
            $builder->orderBy('products_search_index.name_de');
        }

        // Total für Counts
        $total = (clone $builder)->count();
        $items = $builder->limit($limit)->get();

        // Querverweise: Kategoriename laden
        $nodeIds = $items->pluck('master_hierarchy_node_id')->filter()->unique();
        $nodeNames = [];
        if ($nodeIds->isNotEmpty()) {
            $nodeNames = HierarchyNode::whereIn('id', $nodeIds)
                ->pluck('name_de', 'id')
                ->toArray();
        }

        return [
            'total' => $total,
            'items' => $items->map(fn ($p) => [
                'type' => 'product',
                'id' => $p->id,
                'title' => $p->name_de ?: $p->name_en ?: $p->sku,
                'subtitle' => $p->sku ? "SKU: {$p->sku}" : null,
                'image' => $p->primary_image,
                'badges' => array_values(array_filter([
                    $nodeNames[$p->master_hierarchy_node_id] ?? null,
                ])),
                'badge_refs' => array_values(array_filter([
                    $p->master_hierarchy_node_id ? [
                        'type' => 'category',
                        'id' => $p->master_hierarchy_node_id,
                        'label' => $nodeNames[$p->master_hierarchy_node_id] ?? null,
                    ] : null,
                ])),
            ])->values()->toArray(),
        ];
    }

    private function applyProductSearch($builder, string $term): void
    {
        $likeTerm = '%' . $term . '%';

        if (DB::getDriverName() === 'mysql') {
            $phoneticTerm = KoelnerPhonetik::encode($term);

            $builder->where(function ($q) use ($term, $likeTerm, $phoneticTerm) {
                $q->whereRaw(
                    'MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)',
                    [$term . '*']
                )
                ->orWhere('products_search_index.name_de', 'like', $likeTerm)
                ->orWhere('products_search_index.name_en', 'like', $likeTerm)
                ->orWhere('products_search_index.sku', 'like', $likeTerm)
                ->orWhere('products_search_index.ean', 'like', $likeTerm)
                ->orWhereRaw(
                    'products_search_index.searchable_text IS NOT NULL AND MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE)',
                    [$term . '*']
                )
                ->orWhere('products_search_index.searchable_text', 'like', $likeTerm)
                ->orWhere('products_search_index.phonetic_name_de', 'like', '%' . $phoneticTerm . '%')
                ->orWhere('products_search_index.phonetic_text', 'like', '%' . $phoneticTerm . '%');
            });
        } else {
            // SQLite Fallback
            $builder->where(function ($q) use ($likeTerm) {
                $q->where('products_search_index.name_de', 'like', $likeTerm)
                  ->orWhere('products_search_index.name_en', 'like', $likeTerm)
                  ->orWhere('products_search_index.sku', 'like', $likeTerm)
                  ->orWhere('products_search_index.ean', 'like', $likeTerm)
                  ->orWhere('products_search_index.searchable_text', 'like', $likeTerm);
            });
        }
    }

    // ─── Medien ──────────────────────────────────────────

    private function searchMedia(string $query, int $limit): array
    {
        $builder = Media::query();

        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(function ($q) use ($likeTerm) {
                $q->where('file_name', 'like', $likeTerm)
                  ->orWhere('original_file_name', 'like', $likeTerm)
                  ->orWhere('title_de', 'like', $likeTerm)
                  ->orWhere('title_en', 'like', $likeTerm);
            });
        }

        $total = (clone $builder)->count();

        $items = $builder
            ->select(['id', 'file_name', 'original_file_name', 'title_de', 'title_en', 'mime_type', 'media_type'])
            ->orderBy('title_de')
            ->limit($limit)
            ->get();

        // Querverweise: Produktanzahl pro Medium
        $mediaIds = $items->pluck('id');
        $productCounts = [];
        if ($mediaIds->isNotEmpty()) {
            $productCounts = DB::table('product_media_assignments')
                ->whereIn('media_id', $mediaIds)
                ->groupBy('media_id')
                ->select('media_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                ->pluck('cnt', 'media_id')
                ->toArray();
        }

        return [
            'total' => $total,
            'items' => $items->map(fn ($m) => [
                'type' => 'media',
                'id' => $m->id,
                'title' => $m->title_de ?: $m->original_file_name ?: $m->file_name,
                'subtitle' => $m->mime_type,
                'image' => $m->id, // Frontend baut Thumb-URL: /api/v1/media/thumb/{id}
                'media_type' => $m->media_type,
                'badges' => array_values(array_filter([
                    ($productCounts[$m->id] ?? 0) > 0
                        ? ($productCounts[$m->id] . ' ' . (($productCounts[$m->id] ?? 0) === 1 ? 'Produkt' : 'Produkte'))
                        : null,
                ])),
                'badge_refs' => array_values(array_filter([
                    ($productCounts[$m->id] ?? 0) > 0 ? [
                        'type' => 'media_products',
                        'id' => $m->id,
                        'label' => ($productCounts[$m->id]) . ' ' . (($productCounts[$m->id]) === 1 ? 'Produkt' : 'Produkte'),
                    ] : null,
                ])),
            ])->values()->toArray(),
        ];
    }

    // ─── Hierarchien ─────────────────────────────────────

    private function searchHierarchies(string $query, int $limit, ?string $attributeId = null): array
    {
        $builder = HierarchyNode::query()
            ->join('hierarchies', 'hierarchies.id', '=', 'hierarchy_nodes.hierarchy_id')
            ->where('hierarchy_nodes.is_active', true);

        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(function ($q) use ($likeTerm) {
                $q->where('hierarchy_nodes.name_de', 'like', $likeTerm)
                  ->orWhere('hierarchy_nodes.name_en', 'like', $likeTerm);
            });
        }

        // Drill-Down: Nur Knoten mit bestimmtem Attribut
        if ($attributeId) {
            $builder->whereIn('hierarchy_nodes.id', function ($sub) use ($attributeId) {
                $sub->select('hierarchy_node_id')
                    ->from('hierarchy_node_attribute_assignments')
                    ->where('attribute_id', $attributeId)
                    ->distinct();
            });
        }

        $total = (clone $builder)->count();

        $items = $builder
            ->select([
                'hierarchy_nodes.id',
                'hierarchy_nodes.name_de',
                'hierarchy_nodes.name_en',
                'hierarchy_nodes.path',
                'hierarchy_nodes.hierarchy_id',
                'hierarchy_nodes.depth',
                'hierarchies.name_de as hierarchy_name',
            ])
            ->orderBy('hierarchy_nodes.name_de')
            ->limit($limit)
            ->get();

        // Querverweise: Produktanzahl pro Knoten (inkl. Kinder via materialized path)
        $productCounts = [];
        foreach ($items as $node) {
            $count = DB::table('products')
                ->where('status', 'active')
                ->where('product_type_ref', 'product')
                ->whereIn('master_hierarchy_node_id', function ($sub) use ($node) {
                    $sub->select('id')
                        ->from('hierarchy_nodes')
                        ->where('hierarchy_id', $node->hierarchy_id)
                        ->where('path', 'like', $node->path . '%');
                })
                ->count();
            $productCounts[$node->id] = $count;
        }

        return [
            'total' => $total,
            'items' => $items->map(fn ($n) => [
                'type' => 'hierarchy',
                'id' => $n->id,
                'title' => $n->name_de ?: $n->name_en,
                'subtitle' => $n->hierarchy_name,
                'image' => null,
                'path' => $n->path,
                'depth' => $n->depth,
                'badges' => array_values(array_filter([
                    ($productCounts[$n->id] ?? 0) > 0
                        ? ($productCounts[$n->id] . ' ' . (($productCounts[$n->id]) === 1 ? 'Produkt' : 'Produkte'))
                        : null,
                ])),
                'badge_refs' => array_values(array_filter([
                    ($productCounts[$n->id] ?? 0) > 0 ? [
                        'type' => 'category_products',
                        'id' => $n->id,
                        'label' => ($productCounts[$n->id]) . ' ' . (($productCounts[$n->id]) === 1 ? 'Produkt' : 'Produkte'),
                    ] : null,
                ])),
            ])->values()->toArray(),
        ];
    }

    // ─── Attribute ───────────────────────────────────────

    private function searchAttributes(string $query, int $limit): array
    {
        $builder = Attribute::query()
            ->where('status', 'active');

        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(function ($q) use ($likeTerm) {
                $q->where('name_de', 'like', $likeTerm)
                  ->orWhere('name_en', 'like', $likeTerm)
                  ->orWhere('technical_name', 'like', $likeTerm);
            });
        }

        $total = (clone $builder)->count();

        $items = $builder
            ->select(['id', 'name_de', 'name_en', 'technical_name', 'data_type'])
            ->orderBy('name_de')
            ->limit($limit)
            ->get();

        // Querverweise: Produkt- und Kategorieanzahl
        $attrIds = $items->pluck('id');
        $productCounts = [];
        $categoryCounts = [];

        if ($attrIds->isNotEmpty()) {
            $productCounts = DB::table('product_attribute_values')
                ->whereIn('attribute_id', $attrIds)
                ->groupBy('attribute_id')
                ->select('attribute_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                ->pluck('cnt', 'attribute_id')
                ->toArray();

            $categoryCounts = DB::table('hierarchy_node_attribute_assignments')
                ->whereIn('attribute_id', $attrIds)
                ->groupBy('attribute_id')
                ->select('attribute_id', DB::raw('COUNT(DISTINCT hierarchy_node_id) as cnt'))
                ->pluck('cnt', 'attribute_id')
                ->toArray();
        }

        return [
            'total' => $total,
            'items' => $items->map(fn ($a) => [
                'type' => 'attribute',
                'id' => $a->id,
                'title' => $a->name_de ?: $a->name_en,
                'subtitle' => $a->technical_name . ' (' . $a->data_type . ')',
                'image' => null,
                'data_type' => $a->data_type,
                'badges' => array_values(array_filter([
                    ($productCounts[$a->id] ?? 0) > 0
                        ? ($productCounts[$a->id] . ' ' . (($productCounts[$a->id]) === 1 ? 'Produkt' : 'Produkte'))
                        : null,
                    ($categoryCounts[$a->id] ?? 0) > 0
                        ? ($categoryCounts[$a->id] . ' ' . (($categoryCounts[$a->id]) === 1 ? 'Kategorie' : 'Kategorien'))
                        : null,
                ])),
                'badge_refs' => array_values(array_filter([
                    ($productCounts[$a->id] ?? 0) > 0 ? [
                        'type' => 'attribute_products',
                        'id' => $a->id,
                        'label' => ($productCounts[$a->id]) . ' ' . (($productCounts[$a->id]) === 1 ? 'Produkt' : 'Produkte'),
                    ] : null,
                    ($categoryCounts[$a->id] ?? 0) > 0 ? [
                        'type' => 'attribute_categories',
                        'id' => $a->id,
                        'label' => ($categoryCounts[$a->id]) . ' ' . (($categoryCounts[$a->id]) === 1 ? 'Kategorie' : 'Kategorien'),
                    ] : null,
                ])),
            ])->values()->toArray(),
        ];
    }
}
