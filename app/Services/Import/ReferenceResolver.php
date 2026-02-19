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
     * Hierarchieknoten über Pfad → UUID.
     * Der Pfad wird als "Hierarchie/Ebene1/Ebene2/..." erwartet.
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

        // Suche nach exaktem Pfad-Match
        $normalizedPath = '/' . trim($path, '/') . '/';
        $node = $nodes->first(fn($n) => $n->path === $normalizedPath);

        if ($node) {
            return new ResolveResult($node->id, true, null);
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
            unset($this->cache[$entityType]);
        } else {
            $this->cache = [];
        }
    }

    /**
     * Fügt eine neue Entität zum Cache hinzu (nach Anlage während Import).
     */
    public function addToCache(string $entityType, object $entity): void
    {
        if (isset($this->cache[$entityType])) {
            $this->cache[$entityType]->push($entity);
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
        }

        $entities = $this->cache[$cacheKey];
        $trimmedInput = trim($input);

        // 1. Exakter Match auf Primärfeld
        $exact = $entities->first(
            fn($e) => mb_strtolower($e->{$primaryField} ?? '') === mb_strtolower($trimmedInput)
        );
        if ($exact) {
            return new ResolveResult($exact->id, true, null);
        }

        // 2. Exakter Match auf Fallback-Felder (Anzeigenamen)
        foreach ($fallbackFields as $field) {
            $exact = $entities->first(
                fn($e) => mb_strtolower($e->{$field} ?? '') === mb_strtolower($trimmedInput)
            );
            if ($exact) {
                return new ResolveResult($exact->id, true, null);
            }
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
