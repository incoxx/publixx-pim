<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('output_hierarchy_product_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('hierarchy_node_id', 36);
            $table->char('product_id', 36);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('hierarchy_node_id')->references('id')->on('hierarchy_nodes')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['hierarchy_node_id', 'product_id'], 'ohpa_node_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('output_hierarchy_product_assignments');
    }
};
