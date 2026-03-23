<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_mapping_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Quell- und Ziel-Hierarchie (wie bei attribute_mappings)
            $table->foreignUuid('source_hierarchy_id')->constrained('hierarchies')->cascadeOnDelete();
            $table->foreignUuid('target_hierarchy_id')->constrained('hierarchies')->cascadeOnDelete();
            $table->string('name', 255);

            // Bedingung: WENN dieses Quell-Attribut ...
            $table->foreignUuid('condition_attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('condition_operator', 20); // =, !=, >, <, >=, <=, in, not_in, contains, is_empty, is_not_empty
            $table->json('condition_value')->nullable(); // Vergleichswert(e), null bei is_empty/is_not_empty

            // Aktionen: ... DANN setze diese Ziel-Attribute
            // JSON-Array: [{"target_attribute_id": "uuid", "value": "...", "value_type": "static|source_attribute", "source_attribute_id": "uuid"}]
            $table->json('actions');

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['source_hierarchy_id', 'target_hierarchy_id', 'is_active'], 'idx_amr_hierarchies_active');
            $table->index(['condition_attribute_id'], 'idx_amr_condition_attr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_mapping_rules');
    }
};
