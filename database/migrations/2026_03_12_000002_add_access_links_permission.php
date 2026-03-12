<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate([
            'name' => 'access-links.manage',
            'guard_name' => 'sanctum',
        ]);

        // Admin role gets all permissions automatically
        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $admin->syncPermissions(Permission::all());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'access-links.manage')->delete();
    }
};
