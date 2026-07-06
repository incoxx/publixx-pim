<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fügt die Berechtigungen für das E-Commerce-Modul (Warenkorbarten, Adressarten,
 * Zahlungsarten, Bestellungen) additiv hinzu — die Sidebar-Menüpunkte und
 * Controller (EcommerceCartTypeController etc.) referenzieren diese Permissions
 * bereits, ohne dass sie bisher als Permission-Datensätze existierten.
 *
 * Additiv (givePermissionTo) statt syncPermissions, um bestehende Rollen-Rechte
 * nicht zu überschreiben.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $new = [
            'ecommerce-cart-types.view', 'ecommerce-cart-types.create', 'ecommerce-cart-types.edit', 'ecommerce-cart-types.delete',
            'ecommerce-address-types.view', 'ecommerce-address-types.create', 'ecommerce-address-types.edit', 'ecommerce-address-types.delete',
            'ecommerce-payment-types.view', 'ecommerce-payment-types.create', 'ecommerce-payment-types.edit', 'ecommerce-payment-types.delete',
            'ecommerce-orders.view', 'ecommerce-orders.edit',
        ];
        foreach ($new as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        // Vollzugriff: Sysadmin, Admin
        foreach (['Sysadmin', 'Admin'] as $roleName) {
            $this->grant($roleName, $new);
        }

        // Viewer: nur lesen
        $this->grant('Viewer', [
            'ecommerce-cart-types.view', 'ecommerce-address-types.view',
            'ecommerce-payment-types.view', 'ecommerce-orders.view',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'ecommerce-cart-types.view', 'ecommerce-cart-types.create', 'ecommerce-cart-types.edit', 'ecommerce-cart-types.delete',
            'ecommerce-address-types.view', 'ecommerce-address-types.create', 'ecommerce-address-types.edit', 'ecommerce-address-types.delete',
            'ecommerce-payment-types.view', 'ecommerce-payment-types.create', 'ecommerce-payment-types.edit', 'ecommerce-payment-types.delete',
            'ecommerce-orders.view', 'ecommerce-orders.edit',
        ])->where('guard_name', 'sanctum')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param array<int, string> $permissions
     */
    private function grant(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'sanctum')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
};
