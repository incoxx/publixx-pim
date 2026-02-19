<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products_search_index', function (Blueprint $table) {
            $table->char('product_id', 36)->primary();
            $table->string('sku', 100)->nullable();
            $table->string('ean', 20)->nullable();
            $table->string('product_type', 50)->nullable();
            $table->enum('status', ['draft', 'active', 'inactive', 'discontinued'])->nullable();
            $table->string('name_de', 500)->nullable();
            $table->string('name_en', 500)->nullable();
            $table->text('description_de')->nullable();
            $table->string('hierarchy_path', 1000)->nullable();
            $table->string('primary_image', 500)->nullable();
            $table->decimal('list_price', 12, 2)->nullable();
            $table->tinyInteger('attribute_completeness')->unsigned()->nullable();
            $table->string('phonetic_name_de', 100)->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->fullText(['name_de', 'name_en'], 'idx_ft_name');
            $table->fullText('description_de', 'idx_ft_desc');
            $table->index('status', 'idx_status');
            $table->index('product_type', 'idx_type');
            $table->index('sku', 'idx_sku');
            $table->index('list_price', 'idx_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products_search_index');
    }
};
