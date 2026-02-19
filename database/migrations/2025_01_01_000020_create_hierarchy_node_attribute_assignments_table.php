<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hierarchy_node_attribute_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('hierarchy_node_id', 36);
            $table->char('attribute_id', 36);
            $table->string('collection_name', 255)->nullable();
            $table->integer('collection_sort')->default(0);
            $table->integer('attribute_sort')->default(0);
            $table->boolean('dont_inherit')->default(false);
            $table->enum('access_hierarchy', ['hidden', 'visible', 'editable'])->default('visible');
            $table->enum('access_product', ['hidden', 'visible', 'editable'])->default('editable');
            $table->enum('access_variant', ['hidden', 'visible', 'editable'])->default('editable');
            $table->timestamps();

            $table->foreign('hierarchy_node_id')->references('id')->on('hierarchy_nodes')->onDelete('cascade');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->unique(['hierarchy_node_id', 'attribute_id'], 'hnaa_node_attr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hierarchy_node_attribute_assignments');
    }
};
