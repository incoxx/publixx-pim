<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * GET /api/v1/permissions
     *
     * List all available permissions grouped by entity.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Role::class);

        $permissions = Permission::where('guard_name', 'sanctum')
            ->orderBy('name')
            ->pluck('name');

        $grouped = [];
        foreach ($permissions as $permission) {
            $dotPos = strpos($permission, '.');
            if ($dotPos === false) {
                $grouped[$permission] = [];
                continue;
            }
            $entity = substr($permission, 0, $dotPos);
            $action = substr($permission, $dotPos + 1);
            $grouped[$entity][] = $action;
        }

        return response()->json([
            'data' => $grouped,
        ]);
    }
}
