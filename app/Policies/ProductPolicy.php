<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    /**
     * Admin-Wildcard: Admins dürfen alles.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('products.create');
    }

    /**
     * Produkt bearbeiten mit Hierarchie-Einschränkung.
     *
     * Wenn der User `products.edit:node-{uuid}` Permissions hat,
     * darf er NUR Produkte bearbeiten, deren master_hierarchy_node_id
     * unter einem der erlaubten Knoten liegt (Materialized Path Check).
     */
    public function update(User $user, Product $product): bool
    {
        if (! $user->hasPermissionTo('products.edit')) {
            return false;
        }

        return $this->checkHierarchyRestriction($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        if (! $user->hasPermissionTo('products.delete')) {
            return false;
        }

        return $this->checkHierarchyRestriction($user, $product);
    }

    /**
     * Materialized Path Check für Hierarchie-Einschränkung.
     *
     * 1. Alle `products.edit:node-{uuid}` Permissions sammeln
     * 2. Keine vorhanden → keine Einschränkung → true
     * 3. Vorhanden → Produkt muss unter erlaubtem Knoten liegen
     * 4. Prüfung: productNode->path beginnt mit allowedNode->path
     */
    private function checkHierarchyRestriction(User $user, Product $product): bool
    {
        $nodePermissions = $user->getAllPermissions()
            ->filter(fn ($p) => str_starts_with($p->name, 'products.edit:node-'))
            ->values();

        // Keine Einschränkung
        if ($nodePermissions->isEmpty()) {
            return true;
        }

        // Produkt muss einer Hierarchie zugeordnet sein
        if (! $product->master_hierarchy_node_id) {
            return false;
        }

        $allowedNodeIds = $nodePermissions
            ->map(fn ($p) => str_replace('products.edit:node-', '', $p->name))
            ->values();

        $allowedNodes = HierarchyNode::whereIn('id', $allowedNodeIds)->get(['id', 'path']);

        if ($allowedNodes->isEmpty()) {
            return false;
        }

        $productNode = HierarchyNode::find($product->master_hierarchy_node_id, ['id', 'path']);

        if (! $productNode) {
            return false;
        }

        // Materialized Path: Produkt liegt unter erlaubtem Knoten
        // wenn sein Path mit dem Path des erlaubten Knotens beginnt
        foreach ($allowedNodes as $allowedNode) {
            if (str_starts_with($productNode->path, $allowedNode->path)) {
                return true;
            }
        }

        return false;
    }
}
