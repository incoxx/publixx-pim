<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    /**
     * GET /api/v1/roles
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::query();

        if ($request->query('include') === 'permissions') {
            $query->with('permissions');
        }

        $roles = $query->orderBy('name')->get();

        return response()->json([
            'data' => RoleResource::collection($roles),
        ]);
    }

    /**
     * POST /api/v1/roles
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'guard_name' => ['sometimes', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'id' => Str::uuid()->toString(),
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'sanctum',
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $role->load('permissions');

        return response()->json([
            'data' => new RoleResource($role),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/v1/roles/{role}
     */
    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $role->load('permissions');

        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * PUT /api/v1/roles/{role}
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', "unique:roles,name,{$role->id}"],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $role->load('permissions');

        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * DELETE /api/v1/roles/{role}
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->count() > 0) {
            return response()->json([
                'type' => 'https://publixx-pim.local/problems/roles/in-use',
                'title' => 'Role In Use',
                'detail' => "The role '{$role->name}' is still assigned to {$role->users()->count()} user(s).",
                'status' => Response::HTTP_CONFLICT,
            ], Response::HTTP_CONFLICT, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        $role->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * PUT /api/v1/roles/{role}/permissions
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions']);
        $role->load('permissions');

        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }
}
