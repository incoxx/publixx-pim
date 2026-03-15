<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->char('manager_id', 36)->nullable();
                $table->char('parent_project_id', 36)->nullable();
                $table->enum('status', ['planning', 'active', 'completed', 'on_hold'])->default('planning');
                $table->timestamps();

                $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('parent_project_id')->references('id')->on('projects')->nullOnDelete();
                $table->index('status');
            });
        }

        if (!Schema::hasTable('project_team')) {
            Schema::create('project_team', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('project_id', 36);
                $table->char('team_id', 36);
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
                $table->unique(['project_id', 'team_id']);
            });
        }

        if (!Schema::hasTable('project_product')) {
            Schema::create('project_product', function (Blueprint $table) {
                $table->char('id', 36)->primary();
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
        Schema::dropIfExists('project_product');
        Schema::dropIfExists('project_team');
        Schema::dropIfExists('projects');
    }
};
