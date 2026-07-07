<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\AssetCatalogResource;
use App\Http\Resources\Api\V1\MediaUsageTypeResource;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeMediaAssignment;
use App\Models\Media;
use App\Models\MediaAttributeValue;
use App\Models\MediaCountry;
use App\Models\MediaLanguage;
use App\Models\MediaUsageType;
use App\Models\ProductMediaAssignment;
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
        $usageTypeId = $request->query('usage_type');

        $query = Media::query()->with([
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.dictionaryEntry',
            'attributeValues.unit',
            'assetFolder',
            'pdfDocument',
            'hierarchyNodeAssignments.hierarchyNode.hierarchy',
            'mediaLanguage',
            'mediaCountry',
        ])->withCount(['productAssignments', 'hierarchyNodeAssignments']);

        // Anonyme Katalog-Zugriffe: Medientypen mit aktiver RoleEntityRestriction werden
        // pauschal ausgeschlossen. Bei angemeldeten PIM-Nutzern (z.B. interner Bulk-Download
        // aus der Medienverwaltung, der denselben Endpunkt nutzt) greift die individuelle
        // Rechteprüfung der jeweiligen Rolle.
        $query->excludingRestrictionSensitive($request->user());

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

        // Verwendungstyp-Facette: nur Assets, die bei mindestens einem Produkt mit diesem
        // Bildtyp (z.B. Hauptbild/Galleriebild) zugeordnet sind.
        if ($usageTypeId) {
            $query->whereHas('productAssignments', function ($q) use ($usageTypeId) {
                $q->where('usage_type_id', $usageTypeId);
            });
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

            // Medien, die an Hierarchieknoten hängen deren Name matcht
            $mediaIdsFromNodes = HierarchyNodeMediaAssignment::whereHas('hierarchyNode', function ($nq) use ($likeTerm) {
                $nq->where('name_de', 'like', $likeTerm)
                    ->orWhere('name_en', 'like', $likeTerm);
            })
                ->pluck('media_id')
                ->unique()
                ->toArray();

            // Medien, deren zugeordnete Sprache/Land dem Suchbegriff entspricht (z.B. "Deutsch", "DACH")
            $mediaIdsFromLanguageCountry = Media::query()
                ->where(function ($lq) use ($likeTerm) {
                    $lq->whereHas('mediaLanguage', function ($q) use ($likeTerm) {
                        $q->where('name_de', 'like', $likeTerm)->orWhere('name_en', 'like', $likeTerm);
                    })->orWhereHas('mediaCountry', function ($q) use ($likeTerm) {
                        $q->where('name_de', 'like', $likeTerm)->orWhere('name_en', 'like', $likeTerm);
                    });
                })
                ->pluck('id')
                ->toArray();

            $query->where(function ($q) use ($likeTerm, $mediaIdsFromAttributes, $mediaIdsFromPhonetic, $mediaIdsFromNodes, $mediaIdsFromLanguageCountry) {
                $q->where('file_name', 'like', $likeTerm)
                    ->orWhere('title_de', 'like', $likeTerm)
                    ->orWhere('title_en', 'like', $likeTerm)
                    ->orWhere('description_de', 'like', $likeTerm)
                    ->orWhere('description_en', 'like', $likeTerm)
                    ->orWhere('alt_text_de', 'like', $likeTerm)
                    ->orWhere('alt_text_en', 'like', $likeTerm)
                    ->orWhere('keywords', 'like', $likeTerm);

                if (!empty($mediaIdsFromAttributes)) {
                    $q->orWhereIn('id', $mediaIdsFromAttributes);
                }

                if (!empty($mediaIdsFromPhonetic)) {
                    $q->orWhereIn('id', $mediaIdsFromPhonetic);
                }

                if (!empty($mediaIdsFromNodes)) {
                    $q->orWhereIn('id', $mediaIdsFromNodes);
                }

                if (!empty($mediaIdsFromLanguageCountry)) {
                    $q->orWhereIn('id', $mediaIdsFromLanguageCountry);
                }
            });
        }

        // Sortierung: FULLTEXT-Scoring bei MySQL, Post-Query-Fallback bei SQLite
        $useRelevanceSort = $isSearchActive && ($sortField === 'relevance' || $sortField === 'created_at');
        $hasFulltext = $isSearchActive && $useRelevanceSort && DB::getDriverName() === 'mysql';

        if ($hasFulltext) {
            // MATCH AGAINST mit gewichtetem Scoring (Name 10×, Beschreibung 3×)
            $quoted = DB::getPdo()->quote(trim($search) . '*');
            $query->addSelect(['media.*']);
            $query->addSelect(DB::raw(
                '(MATCH(file_name, title_de, title_en) AGAINST(' . $quoted . ' IN BOOLEAN MODE)) * 10 '
                . '+ (MATCH(description_de, description_en) AGAINST(' . $quoted . ' IN BOOLEAN MODE)) * 3 '
                . 'as ft_score'
            ));

            // Boost für Treffer aus Attributen/Knoten/Phonetik (CASE-Expression)
            $boostParts = [];
            foreach ($mediaIdsFromAttributes as $id) {
                $boostParts[$id] = ($boostParts[$id] ?? 0) + 5;
            }
            foreach ($mediaIdsFromNodes as $id) {
                $boostParts[$id] = ($boostParts[$id] ?? 0) + 5;
            }
            foreach ($mediaIdsFromLanguageCountry as $id) {
                $boostParts[$id] = ($boostParts[$id] ?? 0) + 5;
            }
            foreach ($mediaIdsFromPhonetic as $id) {
                $boostParts[$id] = ($boostParts[$id] ?? 0) + 1;
            }

            if (!empty($boostParts)) {
                $caseParts = [];
                foreach ($boostParts as $id => $boost) {
                    $caseParts[] = 'WHEN id = ' . DB::getPdo()->quote($id) . ' THEN ' . $boost;
                }
                $boostExpr = 'CASE ' . implode(' ', $caseParts) . ' ELSE 0 END';
                $query->addSelect(DB::raw("({$boostExpr}) as boost_score"));
                $query->orderByRaw('(ft_score + boost_score) DESC, created_at DESC');
            } else {
                $query->addSelect(DB::raw('0 as boost_score'));
                $query->orderByRaw('ft_score DESC, created_at DESC');
            }
        } else {
            $sortColumn = match ($sortField) {
                'name' => $lang === 'en' ? 'title_en' : 'title_de',
                'file_size' => 'file_size',
                'file_name' => 'file_name',
                'relevance' => 'created_at',
                default => 'created_at',
            };
            $query->orderBy($sortColumn, $sortOrder);
        }

        $paginated = $query->paginate($perPage);

        // Compute match_sources, snippets, relevance_score und references
        if ($isSearchActive) {
            $term = mb_strtolower(trim($search));
            $rawTerm = trim($search);
            $phoneticTerm = KoelnerPhonetik::encode($rawTerm);

            // Produkt-Referenzen vorladen
            $mediaIds = collect($paginated->items())->pluck('id')->toArray();
            $productRefsByMedia = [];
            if (!empty($mediaIds)) {
                $hasSearchIndex = DB::getSchemaBuilder()->hasTable('products_search_index');
                $assignments = ProductMediaAssignment::whereIn('media_id', $mediaIds)
                    ->with(['product' => fn ($q) => $q->where('status', 'active')->select('id', 'sku')])
                    ->get();

                $productNames = [];
                if ($hasSearchIndex) {
                    $productIds = $assignments->pluck('product_id')->unique()->values()->toArray();
                    if (!empty($productIds)) {
                        $nameField = $lang === 'en' ? 'name_en' : 'name_de';
                        $productNames = DB::table('products_search_index')
                            ->whereIn('product_id', $productIds)
                            ->pluck($nameField, 'product_id')
                            ->toArray();
                    }
                }

                foreach ($assignments->groupBy('media_id') as $mId => $group) {
                    $productRefsByMedia[$mId] = $group
                        ->filter(fn ($a) => $a->product !== null)
                        ->map(fn ($a) => [
                            'id' => $a->product->id,
                            'sku' => $a->product->sku,
                            'name' => $productNames[$a->product->id] ?? $a->product->sku,
                        ])
                        ->unique('id')
                        ->values()
                        ->toArray();
                }
            }

            foreach ($paginated->items() as $item) {
                $sources = [];
                $score = 0;

                // Dateiname
                $fileNameLower = mb_strtolower($item->file_name ?? '');
                if ($fileNameLower === $term) {
                    $sources[] = ['type' => 'filename', 'label' => $lang === 'en' ? 'Filename' : 'Dateiname', 'snippet' => $this->extractSnippet($item->file_name, $rawTerm)];
                    $score += 100;
                } elseif (str_contains($fileNameLower, $term)) {
                    $sources[] = ['type' => 'filename', 'label' => $lang === 'en' ? 'Filename' : 'Dateiname', 'snippet' => $this->extractSnippet($item->file_name, $rawTerm)];
                    $score += 50;
                }

                // Titel
                $titleDe = mb_strtolower($item->title_de ?? '');
                $titleEn = mb_strtolower($item->title_en ?? '');
                if (str_contains($titleDe, $term)) {
                    $sources[] = ['type' => 'title', 'label' => $lang === 'en' ? 'Title' : 'Titel', 'snippet' => $this->extractSnippet($item->title_de, $rawTerm)];
                    $score += 40;
                } elseif (str_contains($titleEn, $term)) {
                    $sources[] = ['type' => 'title', 'label' => $lang === 'en' ? 'Title' : 'Titel', 'snippet' => $this->extractSnippet($item->title_en, $rawTerm)];
                    $score += 35;
                }

                // Beschreibung
                $descDe = mb_strtolower($item->description_de ?? '');
                $descEn = mb_strtolower($item->description_en ?? '');
                if (str_contains($descDe, $term)) {
                    $sources[] = ['type' => 'description', 'label' => $lang === 'en' ? 'Description' : 'Beschreibung', 'snippet' => $this->extractSnippet($item->description_de, $rawTerm)];
                    $score += 20;
                } elseif (str_contains($descEn, $term)) {
                    $sources[] = ['type' => 'description', 'label' => $lang === 'en' ? 'Description' : 'Beschreibung', 'snippet' => $this->extractSnippet($item->description_en, $rawTerm)];
                    $score += 20;
                }

                // Attribut-Match
                if ($item->relationLoaded('attributeValues')) {
                    foreach ($item->attributeValues as $attrValue) {
                        $attr = $attrValue->attribute;
                        if (!$attr) continue;
                        $valueStr = mb_strtolower($attrValue->value_string ?? '');
                        if ($valueStr !== '' && str_contains($valueStr, $term)) {
                            $attrName = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;
                            $sources[] = ['type' => 'attribute', 'label' => $attrName, 'snippet' => $this->extractSnippet($attrValue->value_string, $rawTerm)];
                            $score += 25;
                            break;
                        }
                    }
                }

                // Hierarchieknoten-Match
                if ($item->relationLoaded('hierarchyNodeAssignments')) {
                    foreach ($item->hierarchyNodeAssignments as $nodeAssignment) {
                        $node = $nodeAssignment->hierarchyNode;
                        if (!$node) continue;
                        $nodeName = mb_strtolower($node->name_de ?? '');
                        $nodeNameEn = mb_strtolower($node->name_en ?? '');
                        if (($nodeName !== '' && str_contains($nodeName, $term)) || ($nodeNameEn !== '' && str_contains($nodeNameEn, $term))) {
                            $displayName = $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de;
                            $hierarchyName = $node->hierarchy?->name_de ?? '';
                            $label = $lang === 'en' ? "Category: {$displayName}" : "Kategorie: {$displayName}";
                            if ($hierarchyName) {
                                $label .= " ({$hierarchyName})";
                            }
                            $sources[] = ['type' => 'hierarchy_node', 'label' => $label, 'snippet' => $displayName];
                            $score += 25;
                            break;
                        }
                    }
                }

                // Phonetisch (niedrigste Relevanz, nur wenn keine anderen Treffer)
                if (empty($sources) && $phoneticTerm !== '') {
                    $phoneticSnippet = null;
                    $isPhoneticMatch = false;
                    if ($item->file_name) {
                        $filePhonetic = KoelnerPhonetik::encode($item->file_name);
                        if ($filePhonetic !== '' && str_contains($filePhonetic, $phoneticTerm)) {
                            $isPhoneticMatch = true;
                            $phoneticSnippet = $item->file_name;
                        }
                    }
                    if (!$isPhoneticMatch && $item->title_de) {
                        $titlePhonetic = KoelnerPhonetik::encode($item->title_de);
                        if ($titlePhonetic !== '' && str_contains($titlePhonetic, $phoneticTerm)) {
                            $isPhoneticMatch = true;
                            $phoneticSnippet = $item->title_de;
                        }
                    }
                    if ($isPhoneticMatch) {
                        $sources[] = ['type' => 'phonetic', 'label' => $lang === 'en' ? 'Sounds similar' : 'Ähnlich klingend', 'snippet' => $phoneticSnippet];
                        $score += 5;
                    }
                }

                // Fallback
                if (empty($sources)) {
                    $sources[] = ['type' => 'filename', 'label' => $lang === 'en' ? 'Filename' : 'Dateiname', 'snippet' => null];
                }

                $item->match_sources = $sources;

                // Kombinierter Score: FULLTEXT (DB) + PHP-Score
                $ftScore = $hasFulltext ? (float) ($item->ft_score ?? 0) + (float) ($item->boost_score ?? 0) : 0;
                $item->relevance_score = $hasFulltext ? round($ftScore + ($score / 100), 2) : $score;

                // Referenzen (Produkte + Knoten)
                $refs = [];
                $refs['products'] = $productRefsByMedia[$item->id] ?? [];
                if ($item->relationLoaded('hierarchyNodeAssignments')) {
                    $refs['hierarchy_nodes'] = $item->hierarchyNodeAssignments
                        ->map(function ($a) use ($lang) {
                            $node = $a->hierarchyNode;
                            if (!$node) return null;
                            return [
                                'node_id' => $node->id,
                                'node_name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
                            ];
                        })
                        ->filter()
                        ->values()
                        ->toArray();
                }
                $item->references = $refs;
            }

            // PHP-Fallback-Sortierung (nur wenn kein FULLTEXT verfügbar)
            if ($useRelevanceSort && !$hasFulltext) {
                $items = collect($paginated->items())
                    ->sortByDesc('relevance_score')
                    ->values();
                $paginated->setCollection($items);
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
        $this->assertNotRestrictionSensitive($request, $medium);

        $lang = $request->query('lang', 'de');

        $medium->load([
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.dictionaryEntry',
            'attributeValues.unit',
            'assetFolder',
            'pdfDocument',
            'hierarchyNodeAssignments.hierarchyNode.hierarchy',
            'mediaLanguage',
            'mediaCountry',
        ])->loadCount(['productAssignments', 'hierarchyNodeAssignments']);

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

        // Hierarchieknoten-Zuordnungen mit Pfad
        $hierarchyNodes = [];
        if ($medium->relationLoaded('hierarchyNodeAssignments')) {
            foreach ($medium->hierarchyNodeAssignments as $assignment) {
                $node = $assignment->hierarchyNode;
                if (!$node) continue;

                // Pfad-Breadcrumb für diesen Knoten aufbauen
                $nodePath = [];
                $ancestors = HierarchyNode::ancestorsOf($node->path)
                    ->orderBy('depth')
                    ->get();
                foreach ($ancestors as $ancestor) {
                    $nodePath[] = $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de;
                }
                $nodePath[] = $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de;

                $hierarchyNodes[] = [
                    'node_id' => $node->id,
                    'node_name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
                    'hierarchy_id' => $node->hierarchy_id,
                    'hierarchy_name' => $lang === 'en' && $node->hierarchy?->name_en ? $node->hierarchy->name_en : ($node->hierarchy?->name_de ?? ''),
                    'hierarchy_type' => $node->hierarchy?->hierarchy_type,
                    'path' => $nodePath,
                    'path_string' => implode(' > ', $nodePath),
                ];
            }
        }

        return response()->json([
            'data' => (new AssetCatalogResource($medium))
                ->additional(['lang' => $lang, 'breadcrumb' => $breadcrumb, 'hierarchy_nodes' => $hierarchyNodes])
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
        $this->assertNotRestrictionSensitive($request, $medium);

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
     * GET /api/v1/asset-catalog/assets/{media}/nodes
     *
     * Hierarchieknoten, an denen dieses Asset zugeordnet ist.
     */
    public function assetNodes(Request $request, Media $medium): JsonResponse
    {
        $this->assertNotRestrictionSensitive($request, $medium);

        $lang = $request->query('lang', 'de');

        $assignments = $medium->hierarchyNodeAssignments()
            ->with(['hierarchyNode.hierarchy'])
            ->orderBy('sort_order')
            ->get();

        $data = $assignments->map(function ($assignment) use ($lang) {
            $node = $assignment->hierarchyNode;
            if (!$node) return null;

            $nodePath = [];
            $ancestors = HierarchyNode::ancestorsOf($node->path)
                ->orderBy('depth')
                ->get();
            foreach ($ancestors as $ancestor) {
                $nodePath[] = [
                    'id' => $ancestor->id,
                    'name' => $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de,
                ];
            }
            $nodePath[] = [
                'id' => $node->id,
                'name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
            ];

            return [
                'assignment_id' => $assignment->id,
                'node_id' => $node->id,
                'node_name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
                'hierarchy_id' => $node->hierarchy_id,
                'hierarchy_name' => $lang === 'en' && $node->hierarchy?->name_en ? $node->hierarchy->name_en : ($node->hierarchy?->name_de ?? ''),
                'hierarchy_type' => $node->hierarchy?->hierarchy_type,
                'path' => $nodePath,
                'path_string' => collect($nodePath)->pluck('name')->implode(' > '),
            ];
        })->filter()->values();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/asset-catalog/folders
     *
     * Folder tree from asset hierarchy.
     */
    public function folders(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'de');

        // Dieselbe Auswahl-Logik wie im internen Medien-Admin (MediaView.vue::fetchFolders() →
        // hierarchiesApi.list({filters:{hierarchy_type:'asset'}}), was serverseitig per Default
        // nach name_de sortiert und den ersten Treffer nimmt). Beide Stellen müssen dieselbe
        // Hierarchie wählen, sonst legt der Admin Ordner unter einer anderen "asset"-Hierarchie
        // an als der, die der öffentliche Katalog anzeigt — bei mehreren hierarchy_type=asset-
        // Hierarchien (kein Uniqueness-Constraint darauf) sonst nicht deterministisch.
        $hierarchy = Hierarchy::where('hierarchy_type', 'asset')->orderBy('name_de')->first();

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
     * GET /api/v1/asset-catalog/usage-types
     *
     * Verwendungstyp-Facette: Bildtypen (Hauptbild, Galleriebild, ...), die tatsächlich bei
     * mindestens einem Produkt einem Asset zugeordnet sind — für den Such-Filter im Katalog.
     */
    public function usageTypes(): JsonResponse
    {
        $types = MediaUsageType::whereHas('mediaAssignments')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => MediaUsageTypeResource::collection($types)->resolve(),
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

        $mediaItems = Media::whereIn('id', $request->input('media_ids'))
            ->excludingRestrictionSensitive($request->user())
            ->get();

        if ($mediaItems->isEmpty()) {
            return response()->json(['message' => 'Keine Assets gefunden.'], 404);
        }

        $skippedCount = count($request->input('media_ids')) - $mediaItems->count();
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
            'X-Skipped-Count' => (string) $skippedCount,
        ]);
    }

    /**
     * Ein Detail-/Sub-Endpunkt darf ein Medium nicht per direkter ID preisgeben, wenn es
     * über die Listen-Query (excludingRestrictionSensitive) gar nicht erreichbar wäre.
     */
    private function assertNotRestrictionSensitive(Request $request, Media $media): void
    {
        $accessible = Media::whereKey($media->id)
            ->excludingRestrictionSensitive($request->user())
            ->exists();

        if (! $accessible) {
            abort(404);
        }
    }

    /**
     * Extrahiert einen Textausschnitt um den Suchbegriff mit <mark>-Highlighting.
     */
    private function extractSnippet(?string $text, string $term, int $contextChars = 40): ?string
    {
        if ($text === null || $text === '' || $term === '') {
            return null;
        }

        $pos = mb_stripos($text, $term);
        if ($pos === false) {
            return null;
        }

        $termLen = mb_strlen($term);
        $textLen = mb_strlen($text);

        $start = max(0, $pos - $contextChars);
        $end = min($textLen, $pos + $termLen + $contextChars);

        $before = mb_substr($text, $start, $pos - $start);
        $match = mb_substr($text, $pos, $termLen);
        $after = mb_substr($text, $pos + $termLen, $end - $pos - $termLen);

        $snippet = '';
        if ($start > 0) $snippet .= '…';
        $snippet .= htmlspecialchars($before, ENT_QUOTES, 'UTF-8');
        $snippet .= '<mark>' . htmlspecialchars($match, ENT_QUOTES, 'UTF-8') . '</mark>';
        $snippet .= htmlspecialchars($after, ENT_QUOTES, 'UTF-8');
        if ($end < $textLen) $snippet .= '…';

        return $snippet;
    }
}
