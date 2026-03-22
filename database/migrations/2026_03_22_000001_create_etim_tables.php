<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ETIM-Versionen
        Schema::create('etim_versions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('version', 20)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        // 2. ETIM-Gruppen (EG)
        Schema::create('etim_groups', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('etim_version_id', 36);
            $table->string('code', 20);
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['etim_version_id', 'code']);
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
        });

        // 3. ETIM-Klassen (EC)
        Schema::create('etim_classes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('etim_version_id', 36);
            $table->char('etim_group_id', 36)->nullable();
            $table->string('code', 20);
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->text('description_de')->nullable();
            $table->text('description_en')->nullable();
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['etim_version_id', 'code']);
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
            $table->foreign('etim_group_id')->references('id')->on('etim_groups')->onDelete('set null');
        });

        // 4. ETIM-Einheiten (EU)
        Schema::create('etim_units', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('etim_version_id', 36);
            $table->string('code', 20);
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('abbreviation', 20)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['etim_version_id', 'code']);
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
        });

        // 5. ETIM-Merkmale (EF)
        Schema::create('etim_features', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('etim_version_id', 36);
            $table->string('code', 20);
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->text('description_de')->nullable();
            $table->text('description_en')->nullable();
            $table->enum('data_type', ['alphanumeric', 'numeric', 'logical', 'range'])->default('alphanumeric');
            $table->char('etim_unit_id', 36)->nullable();
            $table->timestamps();

            $table->unique(['etim_version_id', 'code']);
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
            $table->foreign('etim_unit_id')->references('id')->on('etim_units')->onDelete('set null');
        });

        // 6. ETIM-Werte (EV)
        Schema::create('etim_values', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('etim_version_id', 36);
            $table->string('code', 20);
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->timestamps();

            $table->unique(['etim_version_id', 'code']);
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
        });

        // 7. Klasse-Merkmal-Zuordnung (welche Features gehören zu welcher Klasse)
        Schema::create('etim_class_features', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('etim_class_id', 36);
            $table->char('etim_feature_id', 36);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();

            $table->unique(['etim_class_id', 'etim_feature_id']);
            $table->foreign('etim_class_id')->references('id')->on('etim_classes')->onDelete('cascade');
            $table->foreign('etim_feature_id')->references('id')->on('etim_features')->onDelete('cascade');
        });

        // 8. Merkmal-Wert-Zuordnung (erlaubte Werte pro Feature)
        Schema::create('etim_feature_values', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('etim_feature_id', 36);
            $table->char('etim_value_id', 36);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['etim_feature_id', 'etim_value_id']);
            $table->foreign('etim_feature_id')->references('id')->on('etim_features')->onDelete('cascade');
            $table->foreign('etim_value_id')->references('id')->on('etim_values')->onDelete('cascade');
        });

        // 9. Mapping: HierarchyNode → ETIM-Klasse
        Schema::create('etim_class_mappings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('hierarchy_node_id', 36);
            $table->char('etim_class_id', 36);
            $table->char('etim_version_id', 36);
            $table->float('confidence')->default(1.0);
            $table->enum('mapping_source', ['manual', 'auto', 'import'])->default('manual');
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->unique(['hierarchy_node_id', 'etim_version_id']);
            $table->foreign('hierarchy_node_id')->references('id')->on('hierarchy_nodes')->onDelete('cascade');
            $table->foreign('etim_class_id')->references('id')->on('etim_classes')->onDelete('cascade');
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // 10. Mapping: Attribut → ETIM-Feature
        Schema::create('etim_feature_mappings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('attribute_id', 36);
            $table->char('etim_feature_id', 36);
            $table->char('etim_version_id', 36);
            $table->float('confidence')->default(1.0);
            $table->enum('mapping_source', ['manual', 'auto', 'import'])->default('manual');
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->unique(['attribute_id', 'etim_version_id']);
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('etim_feature_id')->references('id')->on('etim_features')->onDelete('cascade');
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // 11. Mapping: ValueListEntry → ETIM-Value
        Schema::create('etim_value_mappings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('value_list_entry_id', 36);
            $table->char('etim_value_id', 36);
            $table->char('etim_version_id', 36);
            $table->float('confidence')->default(1.0);
            $table->enum('mapping_source', ['manual', 'auto', 'import'])->default('manual');
            $table->timestamps();

            $table->unique(['value_list_entry_id', 'etim_version_id']);
            $table->foreign('value_list_entry_id')->references('id')->on('value_list_entries')->onDelete('cascade');
            $table->foreign('etim_value_id')->references('id')->on('etim_values')->onDelete('cascade');
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
        });

        // 12. Mapping: Unit → ETIM-Unit
        Schema::create('etim_unit_mappings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('unit_id', 36);
            $table->char('etim_unit_id', 36);
            $table->char('etim_version_id', 36);
            $table->float('confidence')->default(1.0);
            $table->enum('mapping_source', ['manual', 'auto', 'import'])->default('manual');
            $table->timestamps();

            $table->unique(['unit_id', 'etim_version_id']);
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->foreign('etim_unit_id')->references('id')->on('etim_units')->onDelete('cascade');
            $table->foreign('etim_version_id')->references('id')->on('etim_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etim_unit_mappings');
        Schema::dropIfExists('etim_value_mappings');
        Schema::dropIfExists('etim_feature_mappings');
        Schema::dropIfExists('etim_class_mappings');
        Schema::dropIfExists('etim_feature_values');
        Schema::dropIfExists('etim_class_features');
        Schema::dropIfExists('etim_values');
        Schema::dropIfExists('etim_features');
        Schema::dropIfExists('etim_units');
        Schema::dropIfExists('etim_classes');
        Schema::dropIfExists('etim_groups');
        Schema::dropIfExists('etim_versions');
    }
};
