<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait Filterable
{
    /**
     * Apply ?filter[field]=value query params to the query builder.
     */
    protected function applyFilters(Builder $query, ?array $filters): Builder
    {
        if (empty($filters)) {
            return $query;
        }

        foreach ($filters as $field => $value) {
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

            if (str_contains($value, ',')) {
                $query->whereIn($field, explode(',', $value));
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Apply ?sort=field&order=asc|desc sorting.
     */
    protected function applySorting(Builder $query, Request $request, string $defaultSort = 'created_at', string $defaultOrder = 'desc'): Builder
    {
        $sort = $request->query('sort', $defaultSort);
        $order = $request->query('order', $defaultOrder);

        $sort = preg_replace('/[^a-zA-Z0-9_]/', '', $sort);
        $order = in_array(strtolower($order), ['asc', 'desc']) ? strtolower($order) : 'asc';

        return $query->orderBy($sort, $order);
    }

    /**
     * Apply ?search=term full-text or LIKE search on given columns.
     */
    protected function applySearch(Builder $query, Request $request, array $searchColumns = ['name']): Builder
    {
        $search = $request->query('search');

        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search, $searchColumns) {
            foreach ($searchColumns as $col) {
                $q->orWhere($col, 'LIKE', "%{$search}%");
            }
        });
    }

    /**
     * Parse ?include=rel1,rel2 into an array of allowed eager loads.
     */
    protected function parseIncludes(Request $request, array $allowed = []): array
    {
        $include = $request->query('include', '');

        if (empty($include)) {
            return [];
        }

        $requested = array_map('trim', explode(',', $include));

        if (empty($allowed)) {
            return $requested;
        }

        return array_values(array_intersect($requested, $allowed));
    }

    /**
     * Get per_page from request, clamped between 1 and 100, default 25.
     */
    protected function getPerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', '25');

        return max(1, min(100, $perPage));
    }
}
