<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SyncRoleRestrictionsRequest;
use App\Http\Resources\Api\V1\RoleEntityRestrictionResource;
use App\Models\AttributeType;
use App\Models\HierarchyNode;
use App\Models\MediaUsageType;
use App\Models\Role;
use App\Models\RoleEntityRestriction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleRestrictionController extends Controller
{
    private const TYPE_MAP = [
        'attribute-types' => AttributeType::class,
        'media-usage-types' => MediaUsageType::class,
        'hierarchy-nodes' => HierarchyNode::class,
    ];

    /**
     * GET /api/v1/roles/{role}/restrictions
     */
    public function index(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $restrictions = $role->entityRestrictions()
            ->with('restrictable')
            ->get();

        $grouped = [];
        foreach (self::TYPE_MAP as $slug => $modelClass) {
            $items = $restrictions->where('restrictable_type', $modelClass)->values();
            if ($items->isNotEmpty()) {
                $grouped[$slug] = RoleEntityRestrictionResource::collection($items);
            }
        }

        return response()->json(['data' => $grouped]);
    }

    /**
     * GET /api/v1/roles/{role}/restrictions/{type}
     */
    public function show(Role $role, string $type): JsonResponse
    {
        $this->authorize('view', $role);

        $modelClass = $this->resolveModelClass($type);
        if (! $modelClass) {
            return response()->json(['message' => 'Invalid restriction type.'], Response::HTTP_NOT_FOUND);
        }

        $restrictions = $role->entityRestrictions()
            ->where('restrictable_type', $modelClass)
            ->with('restrictable')
            ->get();

        return response()->json([
            'data' => RoleEntityRestrictionResource::collection($restrictions),
        ]);
    }

    /**
     * PUT /api/v1/roles/{role}/restrictions/{type}
     */
    public function sync(SyncRoleRestrictionsRequest $request, Role $role, string $type): JsonResponse
    {
        $this->authorize('update', $role);

        $modelClass = $this->resolveModelClass($type);
        if (! $modelClass) {
            return response()->json(['message' => 'Invalid restriction type.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        // Delete existing restrictions for this type
        $role->entityRestrictions()
            ->where('restrictable_type', $modelClass)
            ->delete();

        // Create new restrictions
        $restrictions = collect($validated['restrictions'] ?? [])->map(function ($item) use ($role, $modelClass) {
            return RoleEntityRestriction::create([
                'role_id' => $role->id,
                'restrictable_type' => $modelClass,
                'restrictable_id' => $item['id'],
                'access_level' => $item['access_level'],
            ]);
        });

        return response()->json([
            'data' => RoleEntityRestrictionResource::collection(
                $restrictions->load('restrictable')
            ),
        ]);
    }

    /**
     * DELETE /api/v1/roles/{role}/restrictions/{type}
     */
    public function destroy(Role $role, string $type): JsonResponse
    {
        $this->authorize('update', $role);

        $modelClass = $this->resolveModelClass($type);
        if (! $modelClass) {
            return response()->json(['message' => 'Invalid restriction type.'], Response::HTTP_NOT_FOUND);
        }

        $role->entityRestrictions()
            ->where('restrictable_type', $modelClass)
            ->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function resolveModelClass(string $type): ?string
    {
        return self::TYPE_MAP[$type] ?? null;
    }
}
