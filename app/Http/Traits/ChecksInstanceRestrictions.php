<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Models\HierarchyNode;
use App\Models\RoleEntityRestriction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ChecksInstanceRestrictions
{
    private static array $restrictionCache = [];

    protected function getRestrictionsForUser(User $user, string $modelClass): Collection
    {
        $cacheKey = $user->id . ':' . $modelClass;

        if (isset(self::$restrictionCache[$cacheKey])) {
            return self::$restrictionCache[$cacheKey];
        }

        $roleIds = $user->roles->pluck('id');

        $restrictions = RoleEntityRestriction::whereIn('role_id', $roleIds)
            ->where('restrictable_type', $modelClass)
            ->get();

        self::$restrictionCache[$cacheKey] = $restrictions;

        return $restrictions;
    }

    protected function checkInstanceAccess(User $user, Model $instance, string $requiredLevel): bool
    {
        $restrictions = $this->getRestrictionsForUser($user, get_class($instance));

        if ($restrictions->isEmpty()) {
            return true;
        }

        $restriction = $restrictions->firstWhere('restrictable_id', $instance->id);

        if (! $restriction) {
            return false;
        }

        return $this->accessLevelSufficient($restriction->access_level, $requiredLevel);
    }

    protected function checkNodeInstanceAccess(User $user, HierarchyNode $node, string $requiredLevel): bool
    {
        $restrictions = $this->getRestrictionsForUser($user, HierarchyNode::class);

        if ($restrictions->isEmpty()) {
            return true;
        }

        $allowedNodeIds = $restrictions->pluck('restrictable_id');
        $allowedNodes = HierarchyNode::whereIn('id', $allowedNodeIds)->get(['id', 'path']);

        foreach ($allowedNodes as $allowedNode) {
            if (str_starts_with($node->path, $allowedNode->path)) {
                $restriction = $restrictions->firstWhere('restrictable_id', $allowedNode->id);
                if ($restriction && $this->accessLevelSufficient($restriction->access_level, $requiredLevel)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function accessLevelSufficient(string $grantedLevel, string $requiredLevel): bool
    {
        $hierarchy = ['read' => 1, 'write' => 2, 'delete' => 3];

        return ($hierarchy[$grantedLevel] ?? 0) >= ($hierarchy[$requiredLevel] ?? 0);
    }
}
