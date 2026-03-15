<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove UUID primary key columns from pivot tables
        // so that Laravel's sync() can work without generating UUIDs.

        $pivots = ['team_user', 'project_team', 'project_product'];

        foreach ($pivots as $table) {
            if (Schema::hasColumn($table, 'id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('id');
                });
            }
        }
    }

    public function down(): void
    {
        $pivots = ['team_user', 'project_team', 'project_product'];

        foreach ($pivots as $table) {
            if (!Schema::hasColumn($table, 'id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->char('id', 36)->primary()->first();
                });
            }
        }
    }
};
