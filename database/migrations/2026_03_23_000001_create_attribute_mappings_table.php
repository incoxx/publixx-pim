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

            // Quell-Schema (z.B. Master-Hierarchie)
            $table->foreignUuid('source_hierarchy_id')->constrained('hierarchies')->cascadeOnDelete();
            $table->foreignUuid('source_attribute_id')->constrained('attributes')->cascadeOnDelete();

            // Ziel-Schema (z.B. ETIM-Klassifikation)
            $table->foreignUuid('target_hierarchy_id')->constrained('hierarchies')->cascadeOnDelete();
            $table->foreignUuid('target_attribute_id')->constrained('attributes')->cascadeOnDelete();

            // Transformation
            $table->string('transform_type', 50)->default('direct'); // direct, unit_convert, value_map
            $table->json('transform_config')->nullable();

            // KI-Metadaten
            $table->boolean('ai_suggested')->default(false);
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->foreignUuid('ai_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ai_confirmed_at')->nullable();

            $table->timestamps();

            // Ein Quell-Attribut kann pro Hierarchie-Paar nur einmal auf ein Ziel-Attribut gemappt werden
            $table->unique(
                ['source_hierarchy_id', 'source_attribute_id', 'target_hierarchy_id', 'target_attribute_id'],
                'attr_map_src_tgt_unique'
            );

            // Schnelle Lookups beim Export: alle Mappings für ein Hierarchie-Paar
            $table->index(['source_hierarchy_id', 'target_hierarchy_id'], 'idx_attr_map_hierarchies');
            $table->index(['target_hierarchy_id', 'target_attribute_id'], 'idx_attr_map_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_mappings');
    }
};
