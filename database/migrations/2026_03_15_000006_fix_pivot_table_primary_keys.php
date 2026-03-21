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
        // SQLite cannot drop PRIMARY KEY columns, so we recreate the tables.

        if (Schema::hasTable('team_user') && Schema::hasColumn('team_user', 'id')) {
            Schema::dropIfExists('team_user');
            Schema::create('team_user', function (Blueprint $table) {
                $table->char('team_id', 36);
                $table->char('user_id', 36);
                $table->timestamps();

                $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['team_id', 'user_id']);
            });
        }

        if (Schema::hasTable('project_team') && Schema::hasColumn('project_team', 'id')) {
            Schema::dropIfExists('project_team');
            Schema::create('project_team', function (Blueprint $table) {
                $table->char('project_id', 36);
                $table->char('team_id', 36);
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
                $table->unique(['project_id', 'team_id']);
            });
        }

        if (Schema::hasTable('project_product') && Schema::hasColumn('project_product', 'id')) {
            Schema::dropIfExists('project_product');
            Schema::create('project_product', function (Blueprint $table) {
                $table->char('project_id', 36);
                $table->char('product_id', 36);
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->unique(['project_id', 'product_id']);
            });
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
