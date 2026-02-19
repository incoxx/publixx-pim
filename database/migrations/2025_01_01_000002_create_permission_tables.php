<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not loaded. Run [php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"] first.');
        }

        Schema::create($tableNames['permissions'] ?? 'permissions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name');
            $table->string('guard_name')->default('sanctum');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'] ?? 'roles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name');
            $table->string('guard_name')->default('sanctum');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'] ?? 'model_has_permissions', function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission) {
            $table->char($pivotPermission, 36);
            $table->string('model_type');
            $table->char($columnNames['model_morph_key'], 36);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'] ?? 'permissions')
                ->onDelete('cascade');
            $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'] ?? 'model_has_roles', function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole) {
            $table->char($pivotRole, 36);
            $table->string('model_type');
            $table->char($columnNames['model_morph_key'], 36);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'] ?? 'roles')
                ->onDelete('cascade');
            $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                'model_has_roles_role_model_type_primary');
        });

        Schema::create($tableNames['role_has_permissions'] ?? 'role_has_permissions', function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->char($pivotPermission, 36);
            $table->char($pivotRole, 36);
            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'] ?? 'permissions')
                ->onDelete('cascade');
            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'] ?? 'roles')
                ->onDelete('cascade');
            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not loaded.');
        }

        Schema::dropIfExists($tableNames['role_has_permissions'] ?? 'role_has_permissions');
        Schema::dropIfExists($tableNames['model_has_roles'] ?? 'model_has_roles');
        Schema::dropIfExists($tableNames['model_has_permissions'] ?? 'model_has_permissions');
        Schema::dropIfExists($tableNames['roles'] ?? 'roles');
        Schema::dropIfExists($tableNames['permissions'] ?? 'permissions');
    }
};
