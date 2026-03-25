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
            $table->foreignUuid('assignment_id')
                ->constrained('output_hierarchy_product_assignments')
                ->cascadeOnDelete();
            $table->foreignUuid('attribute_id')
                ->constrained('attributes')
                ->cascadeOnDelete();
            $table->text('value_string')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_flag')->nullable();
            $table->foreignUuid('value_selection_id')->nullable()
                ->constrained('value_list_entries')
                ->nullOnDelete();
            $table->foreignUuid('unit_id')->nullable()
                ->constrained('units')
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
