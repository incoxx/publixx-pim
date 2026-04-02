<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Http\Controllers\Api\V1\Traits\ProductSearchFilters;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\SearchProfile;
use Illuminate\Database\Eloquent\Builder;

/**
 * Zentrale Stelle zum Anwenden von Suchprofil-Filtern auf Product-Queries.
 *
 * ALLE Stellen in der Anwendung, die ein SearchProfile auswerten,
 * MÜSSEN diesen Builder verwenden. Keine duplizierten Filter-Implementierungen.
 *
 * Unterstützt:
 * - status_filter
 * - search_text (LIKE auf name, sku, ean)
 * - category_ids + include_descendants
 * - attribute_filters (flach, via ProductSearchFilters-Trait)
 * - attribute_filter_groups (verschachtelt AND/OR/NOT, via ProductSearchFilters-Trait)
 * - sort_field + sort_order
 */
class SearchProfileQueryBuilder
{
    use ProductSearchFilters;

    /**
     * Erstellt eine gefilterte Product-Query basierend auf einem Suchprofil.
     *
     * @param  SearchProfile  $profile         Das Suchprofil mit allen Filtern
     * @param  string         $language        Sprache für sprachspezifische Attribut-Filter
     * @param  bool           $mainProductsOnly  true → nur product_type_ref='product' (keine Varianten)
     * @param  bool           $applySort       true → sort_field/sort_order anwenden
     */
    public function apply(
        Builder $query,
        SearchProfile $profile,
        string $language = 'de',
        bool $applySort = false,
    ): Builder {
        // Status
        if ($profile->status_filter) {
            $query->where('status', $profile->status_filter);
        }

        // Freitext-Suche
        if ($profile->search_text) {
            $term = $profile->search_text;
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('sku', 'LIKE', "%{$term}%")
                  ->orWhere('ean', 'LIKE', "%{$term}%");
            });
        }

        // Kategorie-Filter
        if (!empty($profile->category_ids)) {
            $this->applyCategoryFilter(
                $query,
                $profile->category_ids,
                $profile->include_descendants ?? false,
            );
        }

        // Attribut-Filter (Trait-basiert, vollständig)
        $this->filterIdx = 0;
        $this->attributeCache = [];

        foreach ($profile->attribute_filters ?? [] as $idx => $filter) {
            $this->applyAttributeFilter($query, $filter, $idx, $language);
        }

        // Verschachtelte Filtergruppen (AND/OR/NOT)
        $filterGroups = $profile->attribute_filter_groups;
        if ($filterGroups && !empty($filterGroups['rules'] ?? [])) {
            $this->validateFilterGroupDepth($filterGroups);
            $this->preloadFilterAttributes($filterGroups);
            $this->applyAttributeFilterGroups($query, $filterGroups, $language);
        }

        // Sortierung
        if ($applySort) {
            $query->orderBy(
                $profile->sort_field ?? 'name',
                $profile->sort_order ?? 'asc',
            );
        }

        return $query;
    }

    /**
     * Erstellt eine neue Product-Query und wendet das Suchprofil an.
     * Convenience-Methode für den häufigsten Anwendungsfall.
     */
    public function forProducts(
        SearchProfile $profile,
        string $language = 'de',
        bool $mainProductsOnly = true,
        bool $applySort = false,
    ): Builder {
        $query = Product::query();

        if ($mainProductsOnly) {
            $query->where('product_type_ref', 'product');
        }

        return $this->apply($query, $profile, $language, $applySort);
    }

    /**
     * Wendet Suchprofil-Filter auf eine beliebige Query an (per SearchProfile-ID).
     * Gibt false zurück wenn das Profil nicht gefunden wurde.
     */
    public function applyById(
        Builder $query,
        ?string $searchProfileId,
        string $language = 'de',
    ): bool {
        if (!$searchProfileId) {
            return false;
        }

        $profile = SearchProfile::find($searchProfileId);
        if (!$profile) {
            return false;
        }

        $this->apply($query, $profile, $language);
        return true;
    }
}
