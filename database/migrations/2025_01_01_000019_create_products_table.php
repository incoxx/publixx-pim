<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('product_type_id', 36);
            $table->string('sku', 100)->unique();
            $table->string('ean', 20)->nullable();
            $table->string('name', 500);
            $table->enum('status', ['draft', 'active', 'inactive', 'discontinued'])->default('draft');
            $table->enum('product_type_ref', ['product', 'variant'])->default('product');
            $table->char('parent_product_id', 36)->nullable();
            $table->char('master_hierarchy_node_id', 36)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();

            $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('restrict');
            $table->foreign('parent_product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('master_hierarchy_node_id')->references('id')->on('hierarchy_nodes')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('status');
            $table->index('ean');
            $table->index('master_hierarchy_node_id');
            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText('name');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
