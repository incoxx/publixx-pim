<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseViewerController extends Controller
{
    /**
     * Tabellen, die der DB-Viewer NIE ausliefert (Audit M-4): Auth-/Session-/
     * Token-Tabellen enthalten Passwort-Hashes und Sitzungs-Token.
     */
    private const BLOCKED_TABLES = [
        'personal_access_tokens',
        'sessions',
        'password_reset_tokens',
        'password_resets',
    ];

    /**
     * Spalten, deren Werte auch in erlaubten Tabellen maskiert werden.
     */
    private const REDACTED_COLUMNS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'token',
        'secret',
        'api_token',
    ];

    public function tables(): JsonResponse
    {
        $this->assertAdmin();

        $tables = array_values(array_filter(
            Schema::getTableListing(),
            fn (string $t): bool => ! in_array($t, self::BLOCKED_TABLES, true),
        ));

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
        $this->assertAdmin();

        if (! $this->tableIsAccessible($table)) {
            return response()->json(['error' => 'Table not found'], 404);
        }

        $columns = Schema::getColumns($table);

        return response()->json(['data' => $columns]);
    }

    public function rows(Request $request, string $table): JsonResponse
    {
        $this->assertAdmin();

        if (! $this->tableIsAccessible($table)) {
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
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage)->get()
            ->map(fn ($row) => $this->redactRow($row));

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

    /**
     * Der DB-Viewer erlaubt Lesezugriff auf beliebige Tabellen und ist deshalb
     * Admin-only (Audit M-4) — die frueher genutzte 'users.view'-Berechtigung
     * (viewAny) war fuer einen generischen Datenbank-Dump zu schwach.
     */
    private function assertAdmin(): void
    {
        $user = request()->user();
        if (! $user || ! $user->hasRole('Admin')) {
            abort(403, 'Nur Administratoren duerfen den Datenbank-Viewer nutzen.');
        }
    }

    private function tableIsAccessible(string $table): bool
    {
        return in_array($table, Schema::getTableListing(), true)
            && ! in_array($table, self::BLOCKED_TABLES, true);
    }

    /**
     * Maskiert sensible Spaltenwerte in einer Ergebniszeile.
     */
    private function redactRow(object $row): object
    {
        foreach (self::REDACTED_COLUMNS as $col) {
            if (property_exists($row, $col) && $row->{$col} !== null) {
                $row->{$col} = '••• redacted •••';
            }
        }

        return $row;
    }
}
