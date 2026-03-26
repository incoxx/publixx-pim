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
 * Performance-Ziel: <200ms bei 100k Produkten.
 *
 * Optimierungen:
 * - SQL_CALC_FOUND_ROWS-Äquivalent: Count + Limit in einem Query via Window-Function
 * - Hierarchie-Produktzählung: Batch-Query statt N+1
 * - Category-Filter: Subquery statt pluck()->toArray()
 * - Kein separater Count-Request nötig (Counts immer im Response)
 */
class QuickSearchController extends Controller
{
    /**
     * GET /api/v1/quick-search
     *
     * Parameter:
     *   q            - Suchbegriff
     *   type         - products|media|hierarchies|attributes (ohne = alle Counts + aktiver Tab)
     *   limit        - max Ergebnisse (1-50, default 20)
     *   category_id  - Drill-Down: nur Produkte in dieser Kategorie
     *   attribute_id - Drill-Down: nur Produkte/Kategorien mit diesem Attribut
     *   media_id     - Drill-Down: nur Produkte mit diesem Medium
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:500',
            'type' => 'nullable|string|in:products,media,hierarchies,attributes',
            'limit' => 'nullable|integer|min:0|max:50',
            'offset' => 'nullable|integer|min:0',
            'category_id' => 'nullable|string|uuid',
            'attribute_id' => 'nullable|string|uuid',
            'media_id' => 'nullable|string|uuid',
        ]);

        $query = trim($validated['q'] ?? '');
        $type = $validated['type'] ?? null;
        $limit = (int) ($validated['limit'] ?? 20);
        $offset = (int) ($validated['offset'] ?? 0);
        $categoryId = $validated['category_id'] ?? null;
        $attributeId = $validated['attribute_id'] ?? null;
        $mediaId = $validated['media_id'] ?? null;

        // Ohne Typ: Counts für alle 4 Typen + Ergebnisse des ersten Tabs mit Treffern
        if (!$type) {
            // Nur Counts holen (schnelle COUNT-Queries, keine Ergebnisse laden)
            $counts = $this->fetchAllCounts($query, $categoryId, $attributeId, $mediaId);

            return response()->json([
                'query' => $query,
                'counts' => $counts,
                'results' => [],
            ]);
        }

        // Mit Typ: Ergebnisse + Count für diesen Typ
        $result = match ($type) {
            'products' => $this->searchProducts($query, $limit, $offset, $categoryId, $attributeId, $mediaId),
            'media' => $this->searchMedia($query, $limit, $offset),
            'hierarchies' => $this->searchHierarchies($query, $limit, $offset, $attributeId),
            'attributes' => $this->searchAttributes($query, $limit, $offset),
        };

        return response()->json([
            'query' => $query,
            'counts' => [$type => $result['total']],
            'results' => $result['items'],
            'has_more' => ($offset + $limit) < $result['total'],
        ]);
    }

    // ─── Schnelle Counts für alle Typen ──────────────────

    private function fetchAllCounts(string $query, ?string $categoryId, ?string $attributeId, ?string $mediaId): array
    {
        return [
            'products' => $this->countProducts($query, $categoryId, $attributeId, $mediaId),
            'media' => $this->countMedia($query),
            'hierarchies' => $this->countHierarchies($query, $attributeId),
            'attributes' => $this->countAttributes($query),
        ];
    }

    private function countProducts(string $query, ?string $categoryId, ?string $attributeId, ?string $mediaId): int
    {
        $builder = DB::table('products_search_index')
            ->join('products', 'products.id', '=', 'products_search_index.product_id')
            ->where('products.status', 'active')
            ->where('products.product_type_ref', 'product');

        $this->applyProductDrillDown($builder, $categoryId, $attributeId, $mediaId);
        if ($query !== '') {
            $this->applyProductSearchWhere($builder, $query);
        }

        return $builder->count();
    }

    private function countMedia(string $query): int
    {
        $builder = DB::table('media');
        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(function ($q) use ($likeTerm) {
                $q->where('file_name', 'like', $likeTerm)
                  ->orWhere('original_file_name', 'like', $likeTerm)
                  ->orWhere('title_de', 'like', $likeTerm)
                  ->orWhere('title_en', 'like', $likeTerm);
            });
        }
        return $builder->count();
    }

    private function countHierarchies(string $query, ?string $attributeId): int
    {
        $builder = DB::table('hierarchy_nodes')->where('is_active', true);
        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(function ($q) use ($likeTerm) {
                $q->where('name_de', 'like', $likeTerm)
                  ->orWhere('name_en', 'like', $likeTerm);
            });
        }
        if ($attributeId) {
            $builder->whereExists(function ($sub) use ($attributeId) {
                $sub->select(DB::raw(1))
                    ->from('hierarchy_node_attribute_assignments')
                    ->whereColumn('hierarchy_node_id', 'hierarchy_nodes.id')
                    ->where('attribute_id', $attributeId);
            });
        }
        return $builder->count();
    }

    private function countAttributes(string $query): int
    {
        $builder = DB::table('attributes')->where('status', 'active');
        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(function ($q) use ($likeTerm) {
                $q->where('name_de', 'like', $likeTerm)
                  ->orWhere('name_en', 'like', $likeTerm)
                  ->orWhere('technical_name', 'like', $likeTerm);
            });
        }
        return $builder->count();
    }

    // ─── Produkte ────────────────────────────────────────

    private function searchProducts(string $query, int $limit, int $offset, ?string $categoryId, ?string $attributeId, ?string $mediaId): array
    {
        if ($limit === 0) {
            return ['total' => $this->countProducts($query, $categoryId, $attributeId, $mediaId), 'items' => []];
        }

        $builder = ProductSearchIndex::query()
            ->join('products', 'products.id', '=', 'products_search_index.product_id')
            ->where('products.status', 'active')
            ->where('products.product_type_ref', 'product');

        $this->applyProductDrillDown($builder, $categoryId, $attributeId, $mediaId);

        $hasQuery = $query !== '';
        if ($hasQuery) {
            $this->applyProductSearchWhere($builder, $query);
        }

        $builder->select([
            'products.id',
            'products_search_index.sku',
            'products_search_index.name_de',
            'products_search_index.name_en',
            'products_search_index.primary_image',
            'products.master_hierarchy_node_id',
        ]);

        // Relevanz-Score + Sortierung
        if ($hasQuery && DB::getDriverName() === 'mysql') {
            $quoted = DB::getPdo()->quote($query . '*');
            $builder->addSelect(DB::raw(
                "(MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST({$quoted} IN BOOLEAN MODE)) * 10 "
                . "+ IF(products_search_index.searchable_text IS NOT NULL, (MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST({$quoted} IN BOOLEAN MODE)) * 3, 0) "
                . '+ IF(products_search_index.sku = ' . DB::getPdo()->quote($query) . ', 20, 0) '
                . 'as relevance_score'
            ));
            $builder->orderByDesc('relevance_score');
        } else {
            $builder->orderBy('products_search_index.name_de');
        }

        // Count + Ergebnisse: Offset/Limit für Infinite Scroll
        $total = (clone $builder)->count('products.id');
        $items = $builder->offset($offset)->limit($limit)->get();

        // Querverweise: Kategorienamen batch-laden (max 20 IDs)
        $nodeIds = $items->pluck('master_hierarchy_node_id')->filter()->unique()->values();
        $nodeNames = $nodeIds->isNotEmpty()
            ? HierarchyNode::whereIn('id', $nodeIds)->pluck('name_de', 'id')->toArray()
            : [];

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

    /**
     * Drill-Down-Filter auf Produkt-Query anwenden.
     * Nutzt Subqueries statt pluck() → kein PHP-Memory für große Resultsets.
     */
    private function applyProductDrillDown($builder, ?string $categoryId, ?string $attributeId, ?string $mediaId): void
    {
        if ($categoryId) {
            // Subquery: alle Knoten-IDs im Teilbaum (kein pluck → bleibt in MySQL)
            $builder->whereIn('products.master_hierarchy_node_id', function ($sub) use ($categoryId) {
                $sub->select('hn_sub.id')
                    ->from('hierarchy_nodes as hn_sub')
                    ->join('hierarchy_nodes as hn_root', function ($join) {
                        $join->on('hn_sub.hierarchy_id', '=', 'hn_root.hierarchy_id');
                    })
                    ->where('hn_root.id', $categoryId)
                    ->whereColumn(DB::raw("hn_sub.path"), 'like', DB::raw("CONCAT(hn_root.path, '%')"));
            });
        }
        if ($attributeId) {
            $builder->whereExists(function ($sub) use ($attributeId) {
                $sub->select(DB::raw(1))
                    ->from('product_attribute_values')
                    ->whereColumn('product_id', 'products.id')
                    ->where('attribute_id', $attributeId);
            });
        }
        if ($mediaId) {
            $builder->whereExists(function ($sub) use ($mediaId) {
                $sub->select(DB::raw(1))
                    ->from('product_media_assignments')
                    ->whereColumn('product_id', 'products.id')
                    ->where('media_id', $mediaId);
            });
        }
    }

    /**
     * Suchbedingungen auf Produkt-Query anwenden.
     * FULLTEXT zuerst (Index-Scan), LIKE + Phonetik als Fallback.
     */
    private function applyProductSearchWhere($builder, string $term): void
    {
        $likeTerm = '%' . $term . '%';

        if (DB::getDriverName() === 'mysql') {
            $phoneticTerm = KoelnerPhonetik::encode($term);

            $builder->where(function ($q) use ($term, $likeTerm, $phoneticTerm) {
                // FULLTEXT (nutzt Index, sehr schnell)
                $q->whereRaw(
                    'MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)',
                    [$term . '*']
                )
                ->orWhere('products_search_index.sku', 'like', $likeTerm)
                ->orWhere('products_search_index.ean', 'like', $likeTerm)
                ->orWhereRaw(
                    'products_search_index.searchable_text IS NOT NULL AND MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE)',
                    [$term . '*']
                )
                // Phonetik-Fallback (Tippfehler-Toleranz)
                ->orWhere('products_search_index.phonetic_name_de', 'like', '%' . $phoneticTerm . '%');
                // Entfernt: redundante LIKE auf name_de/name_en (FULLTEXT deckt das ab)
                // Entfernt: LIKE auf searchable_text (FULLTEXT deckt das ab)
                // Entfernt: LIKE auf phonetic_text (phonetic_name_de reicht für Schnellsuche)
            });
        } else {
            $builder->where(function ($q) use ($likeTerm) {
                $q->where('products_search_index.name_de', 'like', $likeTerm)
                  ->orWhere('products_search_index.name_en', 'like', $likeTerm)
                  ->orWhere('products_search_index.sku', 'like', $likeTerm)
                  ->orWhere('products_search_index.ean', 'like', $likeTerm);
            });
        }
    }

    // ─── Medien ──────────────────────────────────────────

    private function searchMedia(string $query, int $limit, int $offset = 0): array
    {
        if ($limit === 0) {
            return ['total' => $this->countMedia($query), 'items' => []];
        }

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
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Querverweise: Produktanzahl batch (1 Query für alle Medien)
        $mediaIds = $items->pluck('id');
        $productCounts = $mediaIds->isNotEmpty()
            ? DB::table('product_media_assignments')
                ->whereIn('media_id', $mediaIds)
                ->groupBy('media_id')
                ->select('media_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                ->pluck('cnt', 'media_id')
                ->toArray()
            : [];

        return [
            'total' => $total,
            'items' => $items->map(fn ($m) => [
                'type' => 'media',
                'id' => $m->id,
                'title' => $m->title_de ?: $m->original_file_name ?: $m->file_name,
                'subtitle' => $m->mime_type,
                'image' => $m->id,
                'media_type' => $m->media_type,
                'badges' => array_values(array_filter([
                    ($c = $productCounts[$m->id] ?? 0) > 0
                        ? ($c . ' ' . ($c === 1 ? 'Produkt' : 'Produkte'))
                        : null,
                ])),
                'badge_refs' => array_values(array_filter([
                    ($c = $productCounts[$m->id] ?? 0) > 0 ? [
                        'type' => 'media_products',
                        'id' => $m->id,
                        'label' => $c . ' ' . ($c === 1 ? 'Produkt' : 'Produkte'),
                    ] : null,
                ])),
            ])->values()->toArray(),
        ];
    }

    // ─── Hierarchien ─────────────────────────────────────

    private function searchHierarchies(string $query, int $limit, int $offset = 0, ?string $attributeId = null): array
    {
        if ($limit === 0) {
            return ['total' => $this->countHierarchies($query, $attributeId), 'items' => []];
        }

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

        if ($attributeId) {
            $builder->whereExists(function ($sub) use ($attributeId) {
                $sub->select(DB::raw(1))
                    ->from('hierarchy_node_attribute_assignments')
                    ->whereColumn('hierarchy_node_id', 'hierarchy_nodes.id')
                    ->where('attribute_id', $attributeId);
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
            ->offset($offset)
            ->limit($limit)
            ->get();

        // FIX: Batch-Query statt N+1 Loop.
        // Alle Descendant-Knoten-IDs pro Treffer in einer Query sammeln,
        // dann Produktanzahl per GROUP BY zählen.
        $productCounts = $this->batchCountProductsForNodes($items);

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
                    ($c = $productCounts[$n->id] ?? 0) > 0
                        ? ($c . ' ' . ($c === 1 ? 'Produkt' : 'Produkte'))
                        : null,
                ])),
                'badge_refs' => array_values(array_filter([
                    ($c = $productCounts[$n->id] ?? 0) > 0 ? [
                        'type' => 'category_products',
                        'id' => $n->id,
                        'label' => $c . ' ' . ($c === 1 ? 'Produkt' : 'Produkte'),
                    ] : null,
                ])),
            ])->values()->toArray(),
        ];
    }

    /**
     * Produktanzahl für mehrere Hierarchie-Knoten in einer einzigen Query.
     *
     * Strategie: Für jeden Knoten alle Descendants über materialized path sammeln,
     * dann die Produkte pro Root-Knoten zählen.
     * Bei max 20 Knoten = 1 Query statt 20.
     */
    private function batchCountProductsForNodes($nodes): array
    {
        if ($nodes->isEmpty()) {
            return [];
        }

        // UNION-basierter Ansatz: für jeden Knoten eine Subquery
        $unions = [];
        $bindings = [];

        foreach ($nodes as $node) {
            $unions[] = "SELECT ? as node_id, COUNT(*) as cnt FROM products "
                . "WHERE status = 'active' AND product_type_ref = 'product' "
                . "AND master_hierarchy_node_id IN ("
                . "SELECT id FROM hierarchy_nodes WHERE hierarchy_id = ? AND path LIKE ?"
                . ")";
            $bindings[] = $node->id;
            $bindings[] = $node->hierarchy_id;
            $bindings[] = $node->path . '%';
        }

        $sql = implode(' UNION ALL ', $unions);
        $rows = DB::select($sql, $bindings);

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->node_id] = (int) $row->cnt;
        }

        return $counts;
    }

    // ─── Attribute ───────────────────────────────────────

    private function searchAttributes(string $query, int $limit, int $offset = 0): array
    {
        if ($limit === 0) {
            return ['total' => $this->countAttributes($query), 'items' => []];
        }

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
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Querverweise: Batch-Counts (2 Queries für alle Attribute)
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
                    ($pc = $productCounts[$a->id] ?? 0) > 0
                        ? ($pc . ' ' . ($pc === 1 ? 'Produkt' : 'Produkte'))
                        : null,
                    ($cc = $categoryCounts[$a->id] ?? 0) > 0
                        ? ($cc . ' ' . ($cc === 1 ? 'Kategorie' : 'Kategorien'))
                        : null,
                ])),
                'badge_refs' => array_values(array_filter([
                    ($pc = $productCounts[$a->id] ?? 0) > 0 ? [
                        'type' => 'attribute_products',
                        'id' => $a->id,
                        'label' => $pc . ' ' . ($pc === 1 ? 'Produkt' : 'Produkte'),
                    ] : null,
                    ($cc = $categoryCounts[$a->id] ?? 0) > 0 ? [
                        'type' => 'attribute_categories',
                        'id' => $a->id,
                        'label' => $cc . ' ' . ($cc === 1 ? 'Kategorie' : 'Kategorien'),
                    ] : null,
                ])),
            ])->values()->toArray(),
        ];
    }
}
