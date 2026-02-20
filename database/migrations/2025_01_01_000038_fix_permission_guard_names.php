<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix guard_name from 'web' to 'sanctum' in all Spatie permission tables.
     *
     * This resolves 403 Forbidden errors caused by guard mismatch:
     * the app uses 'sanctum' as default guard, but older seeds
     * may have created roles/permissions with guard_name 'web'.
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->update(['guard_name' => 'sanctum']);

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->update(['guard_name' => 'sanctum']);

        // Clear Spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left empty — reverting guard names is not useful
    }
};
