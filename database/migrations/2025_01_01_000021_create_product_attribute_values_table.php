<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('product_id', 36);
            $table->char('attribute_id', 36);
            $table->text('value_string')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_flag')->nullable();
            $table->char('value_selection_id', 36)->nullable();
            $table->char('unit_id', 36)->nullable();
            $table->char('comparison_operator_id', 36)->nullable();
            $table->string('language', 5)->nullable();
            $table->integer('multiplied_index')->default(0);
            $table->boolean('is_inherited')->default(false);
            $table->char('inherited_from_node_id', 36)->nullable();
            $table->char('inherited_from_product_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('value_selection_id')->references('id')->on('value_list_entries')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');
            $table->foreign('comparison_operator_id')->references('id')->on('comparison_operators')->onDelete('set null');
            $table->foreign('inherited_from_node_id')->references('id')->on('hierarchy_nodes')->onDelete('set null');
            $table->foreign('inherited_from_product_id')->references('id')->on('products')->onDelete('set null');

            // Critical unique constraint (language can be NULL, MySQL treats NULLs as distinct in unique indexes)
            $table->unique(
                ['product_id', 'attribute_id', 'language', 'multiplied_index'],
                'pav_product_attr_lang_idx_unique'
            );
            $table->index(['product_id', 'attribute_id']);
            $table->index(['attribute_id', 'value_string'], 'idx_attr_value_prefix');
            $table->index(['product_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
