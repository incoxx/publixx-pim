<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->foreignUuid('target_attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->foreignUuid('output_hierarchy_id')->constrained('hierarchies')->cascadeOnDelete();

            // Transformation
            $table->string('transform_type', 50)->default('direct'); // direct, unit_convert, value_map
            $table->json('transform_config')->nullable();

            // KI-Metadaten
            $table->boolean('ai_suggested')->default(false);
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->foreignUuid('ai_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ai_confirmed_at')->nullable();

            $table->timestamps();

            // Ein Quell-Attribut kann pro Hierarchie nur einmal auf ein Ziel-Attribut gemappt werden
            $table->unique(
                ['source_attribute_id', 'target_attribute_id', 'output_hierarchy_id'],
                'attr_map_src_tgt_hierarchy_unique'
            );

            // Schnelle Lookups beim Export: alle Mappings für eine Hierarchie
            $table->index(['output_hierarchy_id', 'target_attribute_id'], 'idx_attr_map_hierarchy_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_mappings');
    }
};
