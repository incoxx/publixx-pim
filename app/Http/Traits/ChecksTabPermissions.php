<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Models\RoleTabPermission;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Provides server-side enforcement of product editor tab permissions.
 *
 * Tab permissions restrict what a user can see/edit in the product editor.
 * While primarily a UI concern, we enforce them server-side to prevent
 * bypass via direct API calls.
 */
trait ChecksTabPermissions
{
    /**
     * Assert that the authenticated user has write access to the given product editor tab.
     *
     * @throws AuthorizationException
     */
    protected function assertTabWriteAccess(string $tabKey): void
    {
        $user = auth()->user();

        if (! $user) {
            throw new AuthorizationException('Unauthenticated.');
        }

        // Admin bypass
        if ($user->hasRole('Admin')) {
            return;
        }

        $accessLevel = $this->resolveTabAccessLevel($user, $tabKey);

        if ($accessLevel !== 'write') {
            throw new AuthorizationException(
                "You do not have write access to the '{$tabKey}' section."
            );
        }
    }

    /**
     * Check if the authenticated user can view a given product editor tab.
     */
    protected function canViewTab(string $tabKey): bool
    {
        $user = auth()->user();

        if (! $user || $user->hasRole('Admin')) {
            return true;
        }

        $accessLevel = $this->resolveTabAccessLevel($user, $tabKey);

        return $accessLevel !== 'hidden';
    }

    /**
     * Resolve the effective tab access level for a user across all their roles.
     * The highest access wins (write > read > hidden).
     *
     * Important: A role that has NO entry for a tab grants full (write) access
     * to that tab. Only explicitly set restrictions limit access.
     */
    private function resolveTabAccessLevel($user, string $tabKey): string
    {
        $hierarchy = ['hidden' => 0, 'read' => 1, 'write' => 2];

        $roleIds = $user->roles->pluck('id')->all();

        if (empty($roleIds)) {
            return 'write'; // No roles = full access (backward compatible)
        }

        // Get tab permissions for this tab, grouped by role
        $tabPerms = RoleTabPermission::whereIn('role_id', $roleIds)
            ->where('tab_key', $tabKey)
            ->pluck('access_level', 'role_id')
            ->all();

        // Resolve per-role access: missing entry → 'write' (no restriction)
        $maxLevel = 'hidden';
        foreach ($roleIds as $roleId) {
            $level = $tabPerms[$roleId] ?? 'write';
            if (($hierarchy[$level] ?? 0) > ($hierarchy[$maxLevel] ?? 0)) {
                $maxLevel = $level;
            }
        }

        return $maxLevel;
    }
}
