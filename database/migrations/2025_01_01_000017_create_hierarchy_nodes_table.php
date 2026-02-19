<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hierarchy_nodes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('hierarchy_id', 36);
            $table->char('parent_node_id', 36)->nullable();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->string('path', 1000);
            $table->integer('depth')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('hierarchy_id')->references('id')->on('hierarchies')->onDelete('cascade');
            $table->foreign('parent_node_id')->references('id')->on('hierarchy_nodes')->onDelete('set null');
            $table->index(['hierarchy_id', 'parent_node_id']);
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hierarchy_nodes');
    }
};
