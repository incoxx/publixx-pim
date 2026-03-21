<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Models\HierarchyNode;
use App\Models\RoleEntityRestriction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait FiltersByInstanceRestrictions
{
    protected function applyInstanceRestrictionFilter(Builder $query, string $modelClass, ?User $user = null): void
    {
        $user = $user ?? request()->user();

        if (! $user || $user->hasRole('Admin')) {
            return;
        }

        $restrictions = $this->getInstanceRestrictions($user, $modelClass);

        if ($restrictions->isEmpty()) {
            return;
        }

        $allowedIds = $restrictions->pluck('restrictable_id');
        $query->whereIn($query->getModel()->getTable() . '.id', $allowedIds);
    }

    protected function applyNodeInstanceRestrictionFilter(Builder $query, ?User $user = null): void
    {
        $user = $user ?? request()->user();

        if (! $user || $user->hasRole('Admin')) {
            return;
        }

        $restrictions = $this->getInstanceRestrictions($user, HierarchyNode::class);

        if ($restrictions->isEmpty()) {
            return;
        }

        $allowedNodeIds = $restrictions->pluck('restrictable_id');
        $allowedNodes = HierarchyNode::whereIn('id', $allowedNodeIds)->get(['id', 'path']);

        $query->where(function (Builder $q) use ($allowedNodes) {
            foreach ($allowedNodes as $node) {
                $q->orWhere('path', 'LIKE', $node->path . '%');
            }
        });
    }

    private function getInstanceRestrictions(User $user, string $modelClass): Collection
    {
        $roleIds = $user->roles->pluck('id');

        return RoleEntityRestriction::whereIn('role_id', $roleIds)
            ->where('restrictable_type', $modelClass)
            ->get();
    }
}
