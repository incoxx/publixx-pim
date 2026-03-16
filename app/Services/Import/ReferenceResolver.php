<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ValueList;
use Illuminate\Support\Collection;

/**
 * Löst technische Namen und Anzeigenamen in UUIDs auf.
 * Baut interne Caches auf, um wiederholte DB-Queries zu vermeiden.
 */
class ReferenceResolver
{
    /** @var array<string, Collection> Cache: entity-type → Collection */
    private array $cache = [];

    /** @var array<string, array<string, string>> Indexierter Cache für O(1) Lookups: cacheKey → [lowercase_value → id] */
    private array $indexedCache = [];

    private readonly FuzzyMatcher $fuzzyMatcher;

    public function __construct(?FuzzyMatcher $fuzzyMatcher = null)
    {
        $this->fuzzyMatcher = $fuzzyMatcher ?? new FuzzyMatcher();
    }

    // ──────────────────────────────────────────────
    //  Öffentliche Resolve-Methoden
    // ──────────────────────────────────────────────

    /**
     * Produkttyp → UUID.
     */
    public function resolveProductType(string $name): ResolveResult
    {
        return $this->resolve('product_type', $name, fn() => ProductType::all(), 'technical_name', ['name_de', 'name_en']);
    }

    /**
     * Attributgruppe → UUID.
     */
    public function resolveAttributeType(string $name): ResolveResult
    {
        return $this->resolve('attribute_type', $name, fn() => AttributeType::all(), 'technical_name', ['name_de', 'name_en']);
    }

    /**
     * Einheitengruppe → UUID.
     */
    public function resolveUnitGroup(string $name): ResolveResult
    {
        return $this->resolve('unit_group', $name, fn() => UnitGroup::all(), 'technical_name', ['name_de', 'name_en']);
    }

    /**
     * Einheit → UUID (über Kürzel oder technischen Namen).
     */
    public function resolveUnit(string $name): ResolveResult
    {
        return $this->resolve('unit', $name, fn() => Unit::all(), 'technical_name', ['abbreviation']);
    }

    /**
     * Werteliste → UUID.
     */
    public function resolveValueList(string $name): ResolveResult
    {
        return $this->resolve('value_list', $name, fn() => ValueList::all(), 'technical_name', ['name_de', 'name_en']);
    }

    /**
     * Attribut → UUID (über technischen Namen oder Anzeigename DE/EN).
     */
    public function resolveAttribute(string $name): ResolveResult
    {
        return $this->resolve('attribute', $name, fn() => Attribute::all(), 'technical_name', ['name_de', 'name_en']);
    }

    /**
     * Produkt → UUID (über SKU).
     */
    public function resolveProduct(string $sku): ResolveResult
    {
        return $this->resolve('product', $sku, fn() => Product::all(['id', 'sku', 'name']), 'sku', ['name']);
    }

    /**
     * Hierarchie → UUID.
     */
    public function resolveHierarchy(string $name): ResolveResult
    {
        return $this->resolve('hierarchy', $name, fn() => Hierarchy::all(), 'technical_name', ['name_de', 'name_en']);
    }

    /**
     * Preisart → UUID.
     */
    public function resolvePriceType(string $name): ResolveResult
    {
        return $this->resolve('price_type', $name, fn() => PriceType::all(), 'technical_name', ['name_de', 'name_en']);
    }

    /**
     * Beziehungstyp → UUID.
     */
    public function resolveRelationType(string $name): ResolveResult
    {
        return $this->resolve('relation_type', $name, fn() => ProductRelationType::all(), 'technical_name', ['name_de', 'name_en']);
    }

    /**
     * Hierarchieknoten über Name-Pfad → UUID.
     * Der Pfad wird als "Ebene1/Ebene2/..." erwartet (Knotennamen, nicht UUIDs).
     */
    public function resolveHierarchyNode(string $hierarchyTechName, string $path): ResolveResult
    {
        $cacheKey = "hierarchy_node_{$hierarchyTechName}";

        if (!isset($this->cache[$cacheKey])) {
            $hierarchy = Hierarchy::where('technical_name', $hierarchyTechName)->first();
            if (!$hierarchy) {
                return new ResolveResult(null, false, null);
            }
            $this->cache[$cacheKey] = HierarchyNode::where('hierarchy_id', $hierarchy->id)->get();
        }

        $nodes = $this->cache[$cacheKey];

        // Build name-path index: walk tree by parent_node_id to build name-based paths
        if (!isset($this->indexedCache[$cacheKey])) {
            $indexed = [];
            $byId = $nodes->keyBy('id');

            foreach ($nodes as $n) {
                // Build name path by walking up parent chain
                $parts = [];
                $current = $n;
                while ($current) {
                    $parts[] = $current->name_de;
                    $current = $current->parent_node_id ? ($byId[$current->parent_node_id] ?? null) : null;
                }
                $namePath = '/' . implode('/', array_reverse($parts)) . '/';
                $indexed[$namePath] = $n->id;
            }

            $this->indexedCache[$cacheKey] = $indexed;
        }

        // O(1) lookup by name path
        $normalizedPath = '/' . trim($path, '/') . '/';
        if (isset($this->indexedCache[$cacheKey][$normalizedPath])) {
            return new ResolveResult($this->indexedCache[$cacheKey][$normalizedPath], true, null);
        }

        return new ResolveResult(null, false, null);
    }

    /**
     * Prüft ob eine SKU bereits als Produkt existiert.
     */
    public function productExists(string $sku): ?string
    {
        $result = $this->resolveProduct($sku);
        return $result->found ? $result->id : null;
    }

    /**
     * Prüft ob ein Attribut mit technischem Namen existiert.
     */
    public function attributeExists(string $technicalName): ?string
    {
        $result = $this->resolveAttribute($technicalName);
        return $result->found ? $result->id : null;
    }

    /**
     * Cache leeren (z.B. nach Batch-Import wenn neue Entitäten angelegt wurden).
     */
    public function clearCache(?string $entityType = null): void
    {
        if ($entityType) {
            unset($this->cache[$entityType], $this->indexedCache[$entityType]);
        } else {
            $this->cache = [];
            $this->indexedCache = [];
        }
    }

    /**
     * Fügt eine neue Entität zum Cache hinzu (nach Anlage während Import).
     */
    public function addToCache(string $entityType, object $entity): void
    {
        if (isset($this->cache[$entityType])) {
            $this->cache[$entityType]->push($entity);
            // Indexierten Cache invalidieren, damit er beim nächsten resolve() neu aufgebaut wird
            unset($this->indexedCache[$entityType]);
        }
    }

    // ──────────────────────────────────────────────
    //  Interne Resolve-Logik
    // ──────────────────────────────────────────────

    /**
     * Generische Resolve-Logik mit Cache, exaktem Match und Fuzzy-Fallback.
     *
     * @param string   $cacheKey       Interner Cache-Schlüssel
     * @param string   $input          Vom User eingegebener Wert
     * @param callable $loader         Lädt alle Entitäten aus DB
     * @param string   $primaryField   Hauptfeld für exakten Match (z.B. technical_name)
     * @param string[] $fallbackFields Weitere Felder für exakten/fuzzy Match
     */
    private function resolve(
        string $cacheKey,
        string $input,
        callable $loader,
        string $primaryField,
        array $fallbackFields = [],
    ): ResolveResult {
        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $loader();
            // Indexierten Cache aufbauen für O(1) Lookups
            unset($this->indexedCache[$cacheKey]);
        }

        $entities = $this->cache[$cacheKey];
        $trimmedInput = trim($input);
        $lowerInput = mb_strtolower($trimmedInput);

        // Indexierten Cache aufbauen (einmalig pro Entity-Typ)
        if (!isset($this->indexedCache[$cacheKey])) {
            $indexed = [];
            $allFields = array_merge([$primaryField], $fallbackFields);
            foreach ($entities as $entity) {
                foreach ($allFields as $field) {
                    $value = $entity->{$field} ?? null;
                    if ($value !== null && $value !== '') {
                        $key = mb_strtolower($value);
                        // Erstes Match gewinnt (primaryField kommt zuerst)
                        if (!isset($indexed[$key])) {
                            $indexed[$key] = $entity->id;
                        }
                    }
                }
            }
            $this->indexedCache[$cacheKey] = $indexed;
        }

        // 1+2. O(1) exakter Match auf Primary- und Fallback-Felder
        if (isset($this->indexedCache[$cacheKey][$lowerInput])) {
            return new ResolveResult($this->indexedCache[$cacheKey][$lowerInput], true, null);
        }

        // 3. Fuzzy-Match auf allen Feldern
        $allCandidates = [];
        $candidateMap = []; // candidate-string → entity-id

        foreach ($entities as $entity) {
            $fields = array_merge([$primaryField], $fallbackFields);
            foreach ($fields as $field) {
                $value = $entity->{$field} ?? null;
                if ($value !== null && $value !== '') {
                    $allCandidates[] = $value;
                    $candidateMap[mb_strtolower($value)] = $entity->id;
                }
            }
        }

        $fuzzyResult = $this->fuzzyMatcher->findMatch($trimmedInput, $allCandidates);
        if ($fuzzyResult) {
            $resolvedId = $candidateMap[mb_strtolower($fuzzyResult->match)] ?? null;
            return new ResolveResult($resolvedId, false, $fuzzyResult->match);
        }

        // 4. Nicht gefunden
        return new ResolveResult(null, false, null);
    }
}
