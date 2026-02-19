<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_relation_attribute_values', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('product_relation_id', 36);
            $table->char('attribute_id', 36);

            // Typed value columns (same EAV pattern as product_attribute_values)
            $table->text('value_string')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_flag')->nullable();
            $table->char('value_selection_id', 36)->nullable();

            // Optional qualifiers
            $table->char('unit_id', 36)->nullable();
            $table->string('language', 5)->nullable();
            $table->integer('multiplied_index')->default(0);

            $table->timestamps();

            // Foreign keys
            $table->foreign('product_relation_id')
                ->references('id')->on('product_relations')
                ->onDelete('cascade');
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->onDelete('cascade');
            $table->foreign('value_selection_id')
                ->references('id')->on('value_list_entries')
                ->onDelete('set null');
            $table->foreign('unit_id')
                ->references('id')->on('units')
                ->onDelete('set null');

            // Unique: one value per (relation, attribute, language, multiplied_index)
            $table->unique(
                ['product_relation_id', 'attribute_id', 'language', 'multiplied_index'],
                'prav_relation_attr_lang_idx_unique'
            );
            $table->index(['product_relation_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_relation_attribute_values');
    }
};
