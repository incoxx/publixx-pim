<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_relations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('source_product_id', 36);
            $table->char('target_product_id', 36);
            $table->char('relation_type_id', 36);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('source_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('target_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('relation_type_id')->references('id')->on('product_relation_types')->onDelete('cascade');
            $table->unique(['source_product_id', 'target_product_id', 'relation_type_id'], 'pr_source_target_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_relations');
    }
};
