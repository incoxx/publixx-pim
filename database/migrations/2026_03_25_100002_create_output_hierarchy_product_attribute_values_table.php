<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('output_hierarchy_product_attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assignment_id');
            $table->foreign('assignment_id', 'ohpav_assignment_id_foreign')
                ->references('id')->on('output_hierarchy_product_assignments')
                ->cascadeOnDelete();
            $table->uuid('attribute_id');
            $table->foreign('attribute_id', 'ohpav_attribute_id_foreign')
                ->references('id')->on('attributes')
                ->cascadeOnDelete();
            $table->text('value_string')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_flag')->nullable();
            $table->uuid('value_selection_id')->nullable();
            $table->foreign('value_selection_id', 'ohpav_value_selection_id_foreign')
                ->references('id')->on('value_list_entries')
                ->nullOnDelete();
            $table->uuid('unit_id')->nullable();
            $table->foreign('unit_id', 'ohpav_unit_id_foreign')
                ->references('id')->on('units')
                ->nullOnDelete();
            $table->string('language', 5)->nullable();
            $table->integer('multiplied_index')->default(0);
            $table->timestamps();

            $table->unique(
                ['assignment_id', 'attribute_id', 'language', 'multiplied_index'],
                'ohpav_assignment_attribute_lang_idx_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('output_hierarchy_product_attribute_values');
    }
};
