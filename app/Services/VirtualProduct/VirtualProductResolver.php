<?php

declare(strict_types=1);

namespace App\Services\VirtualProduct;

use App\Models\Product;
use App\Models\VirtualProductDefinition;
use App\Services\Pql\PqlExecutor;
use App\Services\Search\SearchProfileQueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Zentrale Auflösungs-Engine für virtuelle Produkte (dynamische Cluster).
 *
 * ALLE Stellen, die die Mitglieder eines virtuellen Produkts ermitteln,
 * MÜSSEN diesen Resolver verwenden. Mitglieder werden bei jedem Aufruf
 * frisch ("live") aus der Quelle berechnet — es gibt keine Materialisierung.
 *
 * Quellen (source_type):
 *   - search_profile → SearchProfileQueryBuilder
 *   - pql            → PqlExecutor
 *   - manual         → fixe Produktliste (manual_product_ids)
 *
 * Garantien:
 *   - Das Klammer-Produkt selbst ist nie Mitglied seines eigenen Clusters.
 *   - Virtuelle Produkte sind nie Mitglieder (kein Cluster-im-Cluster).
 *   - max_members begrenzt die Gesamtzahl der Mitglieder hart.
 */
class VirtualProductResolver
{
    /**
     * Obergrenze, falls kein max_members gesetzt ist. Entspricht dem
     * PQL-Hard-Limit, damit Suchprofile/PQL nicht unbegrenzt expandieren.
     */
    private const DEFAULT_MAX_MEMBERS = 500;

    public function __construct(
        private readonly SearchProfileQueryBuilder $searchProfileQueryBuilder,
        private readonly PqlExecutor $pqlExecutor,
    ) {}

    /**
     * Löst die Mitglieder eines virtuellen Produkts paginiert auf.
     *
     * @param  array<int, string>  $with  Eager-Load-Relationen für die Produkte
     * @return LengthAwarePaginator<Product>
     */
    public function paginate(
        VirtualProductDefinition $definition,
        int $perPage = 50,
        int $page = 1,
        array $with = [],
    ): LengthAwarePaginator {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $ids = $this->memberIds($definition);
        $total = count($ids);

        $pageIds = array_slice($ids, ($page - 1) * $perPage, $perPage);
        $products = $this->loadOrdered($pageIds, $with);

        return new LengthAwarePaginator(
            $products,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * Liefert die geordneten, gefilterten Mitglieds-IDs des Clusters.
     *
     * @return array<int, string>
     */
    public function memberIds(VirtualProductDefinition $definition): array
    {
        $cap = $this->memberCap($definition);

        $rawIds = match ($definition->source_type) {
            VirtualProductDefinition::SOURCE_SEARCH_PROFILE => $this->fromSearchProfile($definition, $cap),
            VirtualProductDefinition::SOURCE_PQL            => $this->fromPql($definition, $cap),
            VirtualProductDefinition::SOURCE_MANUAL         => $this->fromManual($definition),
            default                                         => [],
        };

        // Klammer-Produkt selbst entfernen, Reihenfolge erhalten
        $rawIds = array_values(array_filter(
            $rawIds,
            static fn (string $id): bool => $id !== $definition->product_id,
        ));

        // Nur existierende Produkte ohne eigene Cluster-Definition zulassen
        // (kein Cluster-im-Cluster) — maßgeblich ist, ob ein Produkt bereits
        // tatsächlich als Cluster konfiguriert ist, nicht ob sein Produkttyp
        // das grundsätzlich erlauben würde.
        $valid = Product::query()
            ->whereIn('id', $rawIds)
            ->whereDoesntHave('virtualDefinition')
            ->pluck('id')
            ->all();
        $validSet = array_flip($valid);

        $ids = array_values(array_filter(
            $rawIds,
            static fn (string $id): bool => isset($validSet[$id]),
        ));

        return array_slice($ids, 0, $cap);
    }

    /**
     * Mitglieder aus einem gespeicherten Suchprofil.
     *
     * @return array<int, string>
     */
    private function fromSearchProfile(VirtualProductDefinition $definition, int $cap): array
    {
        $profile = $definition->searchProfile;
        if ($profile === null) {
            return [];
        }

        return $this->searchProfileQueryBuilder
            ->forProducts($profile, $definition->language ?? 'de', true, true)
            ->limit($cap)
            ->pluck('id')
            ->all();
    }

    /**
     * Mitglieder aus einer PQL-Abfrage.
     *
     * @return array<int, string>
     */
    private function fromPql(VirtualProductDefinition $definition, int $cap): array
    {
        $pql = trim((string) ($definition->pql_query ?? ''));
        if ($pql === '') {
            return [];
        }

        try {
            $result = $this->pqlExecutor->execute(
                $pql,
                [$definition->language ?? 'de'],
                $cap,
                0,
            );
        } catch (\InvalidArgumentException $e) {
            Log::warning('VirtualProductResolver: ungültige PQL-Abfrage', [
                'definition_id' => $definition->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $row): ?string => isset($row['id']) ? (string) $row['id'] : null,
            $result['data'] ?? [],
        )));
    }

    /**
     * Mitglieder aus einer fixen, manuell gepflegten Auswahl (Merkliste).
     *
     * @return array<int, string>
     */
    private function fromManual(VirtualProductDefinition $definition): array
    {
        return array_values(array_map(
            static fn ($id): string => (string) $id,
            $definition->manual_product_ids ?? [],
        ));
    }

    /**
     * Lädt Produkte für eine ID-Liste unter Erhalt der übergebenen Reihenfolge.
     *
     * @param  array<int, string>  $ids
     * @param  array<int, string>  $with
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function loadOrdered(array $ids, array $with)
    {
        if (empty($ids)) {
            return collect();
        }

        $position = array_flip($ids);

        return Product::query()
            ->whereIn('id', $ids)
            ->with($with)
            ->get()
            ->sortBy(static fn (Product $product): int => $position[$product->id] ?? PHP_INT_MAX)
            ->values();
    }

    private function memberCap(VirtualProductDefinition $definition): int
    {
        $max = $definition->max_members;

        if ($max !== null && $max > 0) {
            return min($max, self::DEFAULT_MAX_MEMBERS);
        }

        return self::DEFAULT_MAX_MEMBERS;
    }
}
