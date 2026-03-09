<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseViewerController extends Controller
{
    public function tables(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $tables = Schema::getTableListing();

        $result = [];
        foreach ($tables as $table) {
            $result[] = [
                'name' => $table,
                'rows' => DB::table($table)->count(),
            ];
        }

        return response()->json(['data' => $result]);
    }

    public function columns(string $table): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        if (! in_array($table, Schema::getTableListing(), true)) {
            return response()->json(['error' => 'Table not found'], 404);
        }

        $columns = Schema::getColumns($table);

        return response()->json(['data' => $columns]);
    }

    public function rows(Request $request, string $table): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        if (! in_array($table, Schema::getTableListing(), true)) {
            return response()->json(['error' => 'Table not found'], 404);
        }

        $perPage = min((int) $request->input('per_page', 50), 200);
        $page = max((int) $request->input('page', 1), 1);
        $sortBy = $request->input('sort');
        $sortDir = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $search = $request->input('search', '');

        $query = DB::table($table);

        // Simple search across all string columns
        if ($search !== '') {
            $columns = Schema::getColumns($table);
            $query->where(function ($q) use ($columns, $search) {
                foreach ($columns as $col) {
                    if (in_array($col['type_name'], ['varchar', 'char', 'text', 'longtext', 'mediumtext', 'tinytext', 'string'], true)) {
                        $q->orWhere($col['name'], 'LIKE', "%{$search}%");
                    }
                }
            });
        }

        // Sorting
        if ($sortBy && in_array($sortBy, array_column(Schema::getColumns($table), 'name'), true)) {
            $query->orderBy($sortBy, $sortDir);
        }

        $total = $query->count();
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }
}
