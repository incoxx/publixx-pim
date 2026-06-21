<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\ProductSearchIndex;
use App\Support\KoelnerPhonetik;
use App\Support\ProductSearchTerms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Schnellsuche — Google-artige Suche über Produkte, Medien, Hierarchien und Attribute.
 *
 * Performance-Ziel: <100ms bei 100k Produkten.
 *
 * Optimierungen:
 * - 4 COUNT-Queries → 1 UNION ALL (fetchAllCounts)
 * - LIMIT N+1 statt clone+count (kein doppelter Query)
 * - FULLTEXT-Index first, LIKE/Phonetik nur als Fallback
 * - Querverweise: Batch-Queries, keine N+1
 * - Minimale Response: kein redundantes badges-Array
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
            'limit' => 'nullable|integer|min:0|max:50',
            'offset' => 'nullable|integer|min:0',
            'lang' => 'nullable|string|max:5',
            'category_id' => 'nullable|string|uuid',
            'attribute_id' => 'nullable|string|uuid',
            'media_id' => 'nullable|string|uuid',
        ]);

        $query = trim($validated['q'] ?? '');
        $type = $validated['type'] ?? null;
        $limit = (int) ($validated['limit'] ?? 20);
        $offset = (int) ($validated['offset'] ?? 0);
        $lang = $validated['lang'] ?? 'de';
        $categoryId = $validated['category_id'] ?? null;
        $attributeId = $validated['attribute_id'] ?? null;
        $mediaId = $validated['media_id'] ?? null;

        if (!$type) {
            return response()->json([
                'query' => $query,
                'counts' => $this->fetchAllCountsFast($query, $categoryId, $attributeId, $mediaId),
                'results' => [],
            ]);
        }

        $result = match ($type) {
            'products' => $this->searchProducts($query, $limit, $offset, $lang, $categoryId, $attributeId, $mediaId),
            'media' => $this->searchMedia($query, $limit, $offset),
            'hierarchies' => $this->searchHierarchies($query, $limit, $offset, $attributeId),
            'attributes' => $this->searchAttributes($query, $limit, $offset),
        };

        // Counts nur bei erster Seite mitliefern
        $counts = $offset === 0
            ? $this->fetchAllCountsFast($query, $categoryId, $attributeId, $mediaId)
            : [];

        if ($offset === 0) {
            // Erste Seite: die Einzelsuchen liefern aus Performance-Gründen
            // total = 0 — der korrekte Count kommt aus fetchAllCountsFast
            $total = $counts[$type] ?? count($result['items']);
        } else {
            $total = $result['total'];
            $counts[$type] = $total;
        }

        return response()->json([
            'query' => $query,
            'counts' => $counts,
            'results' => $result['items'],
            'has_more' => ($offset + $limit) < $total,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  COUNTS — 1 UNION ALL statt 4 separate Queries
    // ═══════════════════════════════════════════════════════

    private function fetchAllCountsFast(string $query, ?string $categoryId, ?string $attributeId, ?string $mediaId): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return $this->fetchAllCountsSequential($query, $categoryId, $attributeId, $mediaId);
        }

        $unions = [];
        $bindings = [];

        // ── Products ──
        $productWhere = "p.status = 'active' AND p.product_type_ref = 'product'";
        $productJoin = '';

        if ($query !== '') {
            $productJoin = 'JOIN products_search_index psi ON psi.product_id = p.id';
            $productWhere .= ' AND (' . $this->buildProductSearchSql($query, $bindings) . ')';
        }
        if ($categoryId) {
            $productWhere .= ' AND p.master_hierarchy_node_id IN (SELECT hn.id FROM hierarchy_nodes hn JOIN hierarchy_nodes hr ON hr.id = ? AND hn.hierarchy_id = hr.hierarchy_id WHERE hn.path LIKE CONCAT(hr.path, ?))';
            $bindings[] = $categoryId;
            $bindings[] = '%';
        }
        if ($attributeId) {
            $productWhere .= ' AND EXISTS (SELECT 1 FROM product_attribute_values pav WHERE pav.product_id = p.id AND pav.attribute_id = ?)';
            $bindings[] = $attributeId;
        }
        if ($mediaId) {
            $productWhere .= ' AND EXISTS (SELECT 1 FROM product_media_assignments pma WHERE pma.product_id = p.id AND pma.media_id = ?)';
            $bindings[] = $mediaId;
        }
        $unions[] = "SELECT 'products' as entity, COUNT(*) as cnt FROM products p {$productJoin} WHERE {$productWhere}";

        // ── Media ──
        $mediaWhere = '1=1';
        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $mediaWhere = '(file_name LIKE ? OR original_file_name LIKE ? OR title_de LIKE ? OR title_en LIKE ?)';
            $bindings = array_merge($bindings, [$likeTerm, $likeTerm, $likeTerm, $likeTerm]);
        }
        $unions[] = "SELECT 'media' as entity, COUNT(*) as cnt FROM media WHERE {$mediaWhere}";

        // ── Hierarchies ──
        $hierWhere = 'is_active = 1';
        if ($query !== '') {
            $hierWhere .= ' AND (name_de LIKE ? OR name_en LIKE ?)';
            $bindings[] = $likeTerm;
            $bindings[] = $likeTerm;
        }
        if ($attributeId) {
            $hierWhere .= ' AND EXISTS (SELECT 1 FROM hierarchy_node_attribute_assignments hnaa WHERE hnaa.hierarchy_node_id = hierarchy_nodes.id AND hnaa.attribute_id = ?)';
            $bindings[] = $attributeId;
        }
        $unions[] = "SELECT 'hierarchies' as entity, COUNT(*) as cnt FROM hierarchy_nodes WHERE {$hierWhere}";

        // ── Attributes ──
        $attrWhere = "status = 'active'";
        if ($query !== '') {
            $attrWhere .= ' AND (name_de LIKE ? OR name_en LIKE ? OR technical_name LIKE ?)';
            $bindings[] = $likeTerm;
            $bindings[] = $likeTerm;
            $bindings[] = $likeTerm;
        }
        $unions[] = "SELECT 'attributes' as entity, COUNT(*) as cnt FROM attributes WHERE {$attrWhere}";

        $sql = implode(' UNION ALL ', $unions);
        $rows = DB::select($sql, $bindings);

        $counts = ['products' => 0, 'media' => 0, 'hierarchies' => 0, 'attributes' => 0];
        foreach ($rows as $row) {
            $counts[$row->entity] = (int) $row->cnt;
        }
        return $counts;
    }

    /** Produkt-Search-SQL für UNION (gibt WHERE-Fragment zurück, füllt bindings) */
    private function buildProductSearchSql(string $term, array &$bindings): string
    {
        $bool         = ProductSearchTerms::boolean($term);
        $boolAscii    = ProductSearchTerms::booleanAscii($term);
        $phoneticTerm = KoelnerPhonetik::encode($term);
        $likeTerm     = '%' . $term . '%';

        // Pro Wort +wort* (AND/Prefix) + umlaut-normalisierte Variante — wie MCP search_products
        $sql = "MATCH(psi.name_de, psi.name_en) AGAINST(? IN BOOLEAN MODE)"
            . " OR MATCH(psi.name_de, psi.name_en) AGAINST(? IN BOOLEAN MODE)"
            . " OR psi.sku LIKE ?"
            . " OR psi.ean LIKE ?"
            . " OR (psi.searchable_text IS NOT NULL AND MATCH(psi.searchable_text, psi.media_text) AGAINST(? IN BOOLEAN MODE))"
            . " OR (psi.searchable_text IS NOT NULL AND MATCH(psi.searchable_text, psi.media_text) AGAINST(? IN BOOLEAN MODE))";

        $bindings[] = $bool;
        $bindings[] = $boolAscii;
        $bindings[] = $likeTerm;
        $bindings[] = $likeTerm;
        $bindings[] = $bool;
        $bindings[] = $boolAscii;

        // Phonetik nur bei nicht-leerem Code anhaengen — sonst LIKE '%%' = match-all
        // (z. B. bei rein numerischer SKU-Suche).
        if ($phoneticTerm !== '') {
            $sql .= " OR psi.phonetic_name_de LIKE ?";
            $bindings[] = '%' . $phoneticTerm . '%';
        }

        return $sql;
    }

    /** SQLite-Fallback: sequentielle Counts */
    private function fetchAllCountsSequential(string $query, ?string $categoryId, ?string $attributeId, ?string $mediaId): array
    {
        return [
            'products' => $this->countProductsFallback($query, $categoryId, $attributeId, $mediaId),
            'media' => $this->countMediaFallback($query),
            'hierarchies' => $this->countHierarchiesFallback($query, $attributeId),
            'attributes' => $this->countAttributesFallback($query),
        ];
    }

    private function countProductsFallback(string $q, ?string $cid, ?string $aid, ?string $mid): int
    {
        $b = DB::table('products_search_index')->join('products', 'products.id', '=', 'products_search_index.product_id')
            ->where('products.status', 'active')->where('products.product_type_ref', 'product');
        $this->applyProductDrillDown($b, $cid, $aid, $mid);
        if ($q !== '') { $this->applyProductSearchWhere($b, $q); }
        return $b->count();
    }

    private function countMediaFallback(string $q): int
    {
        $b = DB::table('media');
        if ($q !== '') { $l = '%'.$q.'%'; $b->where(fn($x) => $x->where('file_name','like',$l)->orWhere('original_file_name','like',$l)->orWhere('title_de','like',$l)->orWhere('title_en','like',$l)); }
        return $b->count();
    }

    private function countHierarchiesFallback(string $q, ?string $aid): int
    {
        $b = DB::table('hierarchy_nodes')->where('is_active', true);
        if ($q !== '') { $l = '%'.$q.'%'; $b->where(fn($x) => $x->where('name_de','like',$l)->orWhere('name_en','like',$l)); }
        if ($aid) { $b->whereExists(fn($s) => $s->select(DB::raw(1))->from('hierarchy_node_attribute_assignments')->whereColumn('hierarchy_node_id','hierarchy_nodes.id')->where('attribute_id',$aid)); }
        return $b->count();
    }

    private function countAttributesFallback(string $q): int
    {
        $b = DB::table('attributes')->where('status', 'active');
        if ($q !== '') { $l = '%'.$q.'%'; $b->where(fn($x) => $x->where('name_de','like',$l)->orWhere('name_en','like',$l)->orWhere('technical_name','like',$l)); }
        return $b->count();
    }

    // ═══════════════════════════════════════════════════════
    //  PRODUKTE — FULLTEXT + Relevanz-Score
    // ═══════════════════════════════════════════════════════

    private function searchProducts(string $query, int $limit, int $offset, string $lang, ?string $categoryId, ?string $attributeId, ?string $mediaId): array
    {
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
            'products.name as product_name',
            'products_search_index.sku',
            'products_search_index.name_de',
            'products_search_index.name_en',
            'products_search_index.primary_image',
            'products.master_hierarchy_node_id',
        ]);

        if ($hasQuery && DB::getDriverName() === 'mysql') {
            $bool      = DB::getPdo()->quote(ProductSearchTerms::boolean($query));
            $boolAscii = DB::getPdo()->quote(ProductSearchTerms::booleanAscii($query));
            $skuQuoted = DB::getPdo()->quote($query);
            $builder->addSelect(DB::raw(
                'GREATEST('
                . "(MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST({$bool} IN BOOLEAN MODE)) * 10, "
                . "(MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST({$boolAscii} IN BOOLEAN MODE)) * 10"
                . ') '
                . '+ IF(products_search_index.searchable_text IS NOT NULL, GREATEST('
                . "(MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST({$bool} IN BOOLEAN MODE)) * 3, "
                . "(MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST({$boolAscii} IN BOOLEAN MODE)) * 3"
                . '), 0) '
                . "+ IF(products_search_index.sku = {$skuQuoted}, 50, 0) "
                . "+ IF(products_search_index.ean = {$skuQuoted}, 50, 0) "
                . 'as relevance_score'
            ));
            $builder->orderByDesc('relevance_score');
        } else {
            $builder->orderBy('products_search_index.name_de');
        }

        if ($offset > 0) {
            $items = $builder->offset($offset)->limit($limit + 1)->get();
            $hasMoreItems = $items->count() > $limit;
            if ($hasMoreItems) { $items = $items->slice(0, $limit)->values(); }
            $total = $hasMoreItems ? $offset + $limit + 1 : $offset + $items->count();
        } else {
            $items = $builder->limit($limit)->get();
            $total = 0;
        }

        $productIds = $items->pluck('id')->toArray();

        // ── Querverweise: Kategorienamen batch ──
        $nodeIds = $items->pluck('master_hierarchy_node_id')->filter()->unique()->values();
        $nodeCol = $lang === 'en' ? 'name_en' : 'name_de';
        $nodeNames = $nodeIds->isNotEmpty()
            ? HierarchyNode::whereIn('id', $nodeIds)->pluck($nodeCol, 'id')->toArray()
            : [];

        // ── Schnellsuche-Attribute: Snippet (Google-Teaser) ──
        $snippets = $this->loadQuickSearchSnippets($productIds, $lang);

        // ── Produktbeziehungen batch ──
        $relations = $this->loadProductRelations($productIds, $lang);

        return [
            'total' => $total,
            'items' => $items->map(function ($p) use ($lang, $nodeNames, $snippets, $relations) {
                // Produktname: products.name (Stammdaten) hat Vorrang,
                // dann sprachspezifischer Attributname, dann SKU als letzter Fallback
                $attrName = $lang === 'en'
                    ? ($p->name_en ?: $p->name_de)
                    : ($p->name_de ?: $p->name_en);
                $title = $p->product_name ?: ($attrName ?: $p->sku);

                $badges = [];
                if ($p->master_hierarchy_node_id && isset($nodeNames[$p->master_hierarchy_node_id])) {
                    $badges[] = ['type' => 'category', 'id' => $p->master_hierarchy_node_id, 'label' => $nodeNames[$p->master_hierarchy_node_id]];
                }

                return [
                    'type' => 'product',
                    'id' => $p->id,
                    'title' => $title,
                    'subtitle' => $p->sku ? "SKU: {$p->sku}" : null,
                    'image' => $p->primary_image,
                    'snippet' => $snippets[$p->id] ?? null,
                    'relations' => $relations[$p->id] ?? [],
                    'badge_refs' => $badges,
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Schnellsuche-Attribute laden: Kurzer Teaser (Google-Stil).
     *
     * Holt nur Attribute mit is_quick_search=true, max 120 Zeichen.
     * Batch-Query für alle Produkte gleichzeitig.
     */
    private function loadQuickSearchSnippets(array $productIds, string $lang): array
    {
        if (empty($productIds)) { return []; }

        $nameCol = $lang === 'en' ? 'a.name_en' : 'a.name_de';
        $valueCol = 'pav.value_string';

        // Attributwerte für is_quick_search=true holen (inkl. Number + Selection)
        $rows = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->leftJoin('value_list_entries as vle', 'vle.id', '=', 'pav.value_selection_id')
            ->leftJoin('units as u', 'u.id', '=', 'pav.unit_id')
            ->whereIn('pav.product_id', $productIds)
            ->where('a.is_quick_search', true)
            ->where('a.status', 'active')
            ->where(function ($q) use ($lang) {
                // Sprachfilter: nicht-übersetzbare oder passende Sprache
                $q->whereNull('pav.language')
                  ->orWhere('pav.language', $lang);
            })
            ->where('pav.multiplied_index', 0) // Nur Hauptwert
            ->select([
                'pav.product_id',
                DB::raw("COALESCE({$nameCol}, a.name_de) as attr_name"),
                'a.data_type',
                'a.position',
                $valueCol,
                'pav.value_number',
                'pav.value_flag',
                DB::raw($lang === 'en'
                    ? "COALESCE(vle.display_value_en, vle.display_value_de) as selection_value"
                    : "COALESCE(vle.display_value_de, vle.display_value_en) as selection_value"),
                DB::raw("u.abbreviation as unit_abbr"),
            ])
            ->orderBy('a.position')
            ->get();

        // Pro Produkt: "Attributname: Wert · Attributname: Wert" — max 120 Zeichen
        $snippets = [];
        foreach ($rows as $row) {
            $pid = $row->product_id;
            if (!isset($snippets[$pid])) { $snippets[$pid] = []; }

            // Wert bestimmen
            $value = match (true) {
                $row->selection_value !== null => $row->selection_value,
                $row->value_string !== null => strip_tags($row->value_string),
                $row->value_number !== null => rtrim(rtrim(number_format((float) $row->value_number, 4, ',', '.'), '0'), ',')
                    . ($row->unit_abbr ? ' ' . $row->unit_abbr : ''),
                $row->value_flag !== null => ($row->value_flag ? 'Ja' : 'Nein'),
                default => null,
            };

            if ($value !== null && $value !== '') {
                $snippets[$pid][] = $row->attr_name . ': ' . $value;
            }
        }

        // Zusammenbauen + kürzen
        $result = [];
        foreach ($snippets as $pid => $parts) {
            $text = implode(' · ', $parts);
            if (mb_strlen($text) > 120) {
                $text = mb_substr($text, 0, 117) . '...';
            }
            $result[$pid] = $text;
        }
        return $result;
    }

    /**
     * Produktbeziehungen batch laden.
     *
     * Gibt pro Produkt: [{type_name, target_name, target_id, target_sku}]
     * Max 3 Relationen pro Produkt (Teaser).
     */
    private function loadProductRelations(array $productIds, string $lang): array
    {
        if (empty($productIds)) { return []; }

        $nameCol = $lang === 'en' ? 'psi.name_en' : 'psi.name_de';
        $typeNameCol = $lang === 'en' ? 'COALESCE(prt.name_en, prt.name_de)' : 'COALESCE(prt.name_de, prt.name_en)';

        $rows = DB::table('product_relations as pr')
            ->join('product_relation_types as prt', 'prt.id', '=', 'pr.relation_type_id')
            ->join('products as tp', 'tp.id', '=', 'pr.target_product_id')
            ->leftJoin('products_search_index as psi', 'psi.product_id', '=', 'pr.target_product_id')
            ->whereIn('pr.source_product_id', $productIds)
            ->where('tp.status', 'active')
            ->select([
                'pr.source_product_id',
                'pr.target_product_id',
                DB::raw("{$typeNameCol} as relation_type"),
                DB::raw("COALESCE({$nameCol}, psi.name_de, tp.name, psi.sku) as target_name"),
                'psi.sku as target_sku',
            ])
            ->orderBy('pr.sort_order')
            ->get();

        $relations = [];
        foreach ($rows as $row) {
            $pid = $row->source_product_id;
            if (!isset($relations[$pid])) { $relations[$pid] = []; }
            if (count($relations[$pid]) >= 3) { continue; } // Max 3 pro Produkt
            $relations[$pid][] = [
                'type' => $row->relation_type,
                'id' => $row->target_product_id,
                'name' => $row->target_name,
                'sku' => $row->target_sku,
            ];
        }
        return $relations;
    }

    private function applyProductDrillDown($builder, ?string $categoryId, ?string $attributeId, ?string $mediaId): void
    {
        if ($categoryId) {
            $builder->whereIn('products.master_hierarchy_node_id', function ($sub) use ($categoryId) {
                $sub->select('hn_sub.id')
                    ->from('hierarchy_nodes as hn_sub')
                    ->join('hierarchy_nodes as hn_root', fn ($j) => $j->on('hn_sub.hierarchy_id', '=', 'hn_root.hierarchy_id'))
                    ->where('hn_root.id', $categoryId)
                    ->whereColumn(DB::raw('hn_sub.path'), 'like', DB::raw("CONCAT(hn_root.path, '%')"));
            });
        }
        if ($attributeId) {
            $builder->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('product_attribute_values')->whereColumn('product_id', 'products.id')->where('attribute_id', $attributeId));
        }
        if ($mediaId) {
            $builder->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('product_media_assignments')->whereColumn('product_id', 'products.id')->where('media_id', $mediaId));
        }
    }

    private function applyProductSearchWhere($builder, string $term): void
    {
        $likeTerm = '%' . $term . '%';

        if (DB::getDriverName() === 'mysql') {
            $bool         = ProductSearchTerms::boolean($term);
            $boolAscii    = ProductSearchTerms::booleanAscii($term);
            $phoneticTerm = KoelnerPhonetik::encode($term);
            $builder->where(function ($q) use ($bool, $boolAscii, $likeTerm, $phoneticTerm) {
                $q->whereRaw('MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)', [$bool])
                    ->orWhereRaw('MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)', [$boolAscii])
                    ->orWhere('products_search_index.sku', 'like', $likeTerm)
                    ->orWhere('products_search_index.ean', 'like', $likeTerm)
                    ->orWhereRaw('products_search_index.searchable_text IS NOT NULL AND MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE)', [$bool])
                    ->orWhereRaw('products_search_index.searchable_text IS NOT NULL AND MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE)', [$boolAscii]);
                // Phonetik nur bei nicht-leerem Code (sonst LIKE '%%' = match-all, z. B. numerische SKU)
                if ($phoneticTerm !== '') {
                    $q->orWhere('products_search_index.phonetic_name_de', 'like', '%' . $phoneticTerm . '%');
                }
            });
        } else {
            $builder->where(fn ($q) => $q->where('products_search_index.name_de', 'like', $likeTerm)->orWhere('products_search_index.name_en', 'like', $likeTerm)->orWhere('products_search_index.sku', 'like', $likeTerm)->orWhere('products_search_index.ean', 'like', $likeTerm));
        }
    }

    // ═══════════════════════════════════════════════════════
    //  MEDIEN
    // ═══════════════════════════════════════════════════════

    private function searchMedia(string $query, int $limit, int $offset = 0): array
    {
        $builder = Media::query();

        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(fn ($q) => $q->where('file_name', 'like', $likeTerm)->orWhere('original_file_name', 'like', $likeTerm)->orWhere('title_de', 'like', $likeTerm)->orWhere('title_en', 'like', $likeTerm));
        }

        $builder->select(['id', 'file_name', 'original_file_name', 'title_de', 'title_en', 'mime_type', 'media_type'])->orderBy('title_de');

        // LIMIT N+1 bei Nachladen, normaler Limit bei erster Seite
        if ($offset > 0) {
            $items = $builder->offset($offset)->limit($limit + 1)->get();
            $hasMoreItems = $items->count() > $limit;
            if ($hasMoreItems) { $items = $items->slice(0, $limit)->values(); }
            $total = $hasMoreItems ? $offset + $limit + 1 : $offset + $items->count();
        } else {
            $items = $builder->limit($limit)->get();
            $total = 0;
        }

        // Querverweise: batch
        $mediaIds = $items->pluck('id');
        $productCounts = $mediaIds->isNotEmpty()
            ? DB::table('product_media_assignments')->whereIn('media_id', $mediaIds)->groupBy('media_id')
                ->select('media_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))->pluck('cnt', 'media_id')->toArray()
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
                'badge_refs' => ($c = $productCounts[$m->id] ?? 0) > 0 ? [[
                    'type' => 'media_products', 'id' => $m->id,
                    'label' => $c . ' ' . ($c === 1 ? 'Produkt' : 'Produkte'),
                ]] : [],
            ])->values()->toArray(),
        ];
    }

    // ═══════════════════════════════════════════════════════
    //  HIERARCHIEN
    // ═══════════════════════════════════════════════════════

    private function searchHierarchies(string $query, int $limit, int $offset = 0, ?string $attributeId = null): array
    {
        $builder = HierarchyNode::query()
            ->join('hierarchies', 'hierarchies.id', '=', 'hierarchy_nodes.hierarchy_id')
            ->where('hierarchy_nodes.is_active', true);

        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(fn ($q) => $q->where('hierarchy_nodes.name_de', 'like', $likeTerm)->orWhere('hierarchy_nodes.name_en', 'like', $likeTerm));
        }

        if ($attributeId) {
            $builder->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('hierarchy_node_attribute_assignments')->whereColumn('hierarchy_node_id', 'hierarchy_nodes.id')->where('attribute_id', $attributeId));
        }

        $builder->select([
            'hierarchy_nodes.id', 'hierarchy_nodes.name_de', 'hierarchy_nodes.name_en',
            'hierarchy_nodes.path', 'hierarchy_nodes.hierarchy_id', 'hierarchy_nodes.depth',
            'hierarchies.name_de as hierarchy_name',
        ])->orderBy('hierarchy_nodes.name_de');

        if ($offset > 0) {
            $items = $builder->offset($offset)->limit($limit + 1)->get();
            $hasMoreItems = $items->count() > $limit;
            if ($hasMoreItems) { $items = $items->slice(0, $limit)->values(); }
            $total = $hasMoreItems ? $offset + $limit + 1 : $offset + $items->count();
        } else {
            $items = $builder->limit($limit)->get();
            $total = 0;
        }

        // Batch product counts
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
                'badge_refs' => ($c = $productCounts[$n->id] ?? 0) > 0 ? [[
                    'type' => 'category_products', 'id' => $n->id,
                    'label' => $c . ' ' . ($c === 1 ? 'Produkt' : 'Produkte'),
                ]] : [],
            ])->values()->toArray(),
        ];
    }

    private function batchCountProductsForNodes($nodes): array
    {
        if ($nodes->isEmpty()) { return []; }

        $unions = [];
        $bindings = [];

        foreach ($nodes as $node) {
            $unions[] = "SELECT ? as node_id, COUNT(*) as cnt FROM products WHERE status = 'active' AND product_type_ref = 'product' AND master_hierarchy_node_id IN (SELECT id FROM hierarchy_nodes WHERE hierarchy_id = ? AND path LIKE ?)";
            $bindings[] = $node->id;
            $bindings[] = $node->hierarchy_id;
            $bindings[] = $node->path . '%';
        }

        $rows = DB::select(implode(' UNION ALL ', $unions), $bindings);
        $counts = [];
        foreach ($rows as $row) { $counts[$row->node_id] = (int) $row->cnt; }
        return $counts;
    }

    // ═══════════════════════════════════════════════════════
    //  ATTRIBUTE
    // ═══════════════════════════════════════════════════════

    private function searchAttributes(string $query, int $limit, int $offset = 0): array
    {
        $builder = Attribute::query()->where('status', 'active');

        if ($query !== '') {
            $likeTerm = '%' . $query . '%';
            $builder->where(fn ($q) => $q->where('name_de', 'like', $likeTerm)->orWhere('name_en', 'like', $likeTerm)->orWhere('technical_name', 'like', $likeTerm));
        }

        $builder->select(['id', 'name_de', 'name_en', 'technical_name', 'data_type'])->orderBy('name_de');

        if ($offset > 0) {
            $items = $builder->offset($offset)->limit($limit + 1)->get();
            $hasMoreItems = $items->count() > $limit;
            if ($hasMoreItems) { $items = $items->slice(0, $limit)->values(); }
            $total = $hasMoreItems ? $offset + $limit + 1 : $offset + $items->count();
        } else {
            $items = $builder->limit($limit)->get();
            $total = 0;
        }

        $attrIds = $items->pluck('id');
        $productCounts = [];
        $categoryCounts = [];

        if ($attrIds->isNotEmpty()) {
            $productCounts = DB::table('product_attribute_values')->whereIn('attribute_id', $attrIds)->groupBy('attribute_id')
                ->select('attribute_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))->pluck('cnt', 'attribute_id')->toArray();
            $categoryCounts = DB::table('hierarchy_node_attribute_assignments')->whereIn('attribute_id', $attrIds)->groupBy('attribute_id')
                ->select('attribute_id', DB::raw('COUNT(DISTINCT hierarchy_node_id) as cnt'))->pluck('cnt', 'attribute_id')->toArray();
        }

        return [
            'total' => $total,
            'items' => $items->map(function ($a) use ($productCounts, $categoryCounts) {
                $refs = [];
                if (($pc = $productCounts[$a->id] ?? 0) > 0) {
                    $refs[] = ['type' => 'attribute_products', 'id' => $a->id, 'label' => $pc . ' ' . ($pc === 1 ? 'Produkt' : 'Produkte')];
                }
                if (($cc = $categoryCounts[$a->id] ?? 0) > 0) {
                    $refs[] = ['type' => 'attribute_categories', 'id' => $a->id, 'label' => $cc . ' ' . ($cc === 1 ? 'Kategorie' : 'Kategorien')];
                }
                return [
                    'type' => 'attribute', 'id' => $a->id,
                    'title' => $a->name_de ?: $a->name_en,
                    'subtitle' => $a->technical_name . ' (' . $a->data_type . ')',
                    'image' => null, 'data_type' => $a->data_type,
                    'badge_refs' => $refs,
                ];
            })->values()->toArray(),
        ];
    }
}
