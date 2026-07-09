<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_types', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->json('default_attribute_groups')->nullable();
            $table->json('default_item_attribute_groups')->nullable();
            // FK auf collection_render_templates folgt in einer additiven Migration (Phase 2),
            // sobald die Render-Template-Zieltabelle feststeht.
            $table->char('default_render_template_id', 36)->nullable();
            $table->boolean('requires_organization')->default(false);
            $table->boolean('requires_snapshot')->default(false);
            $table->json('allowed_export_formats')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('sort_order');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_types');
    }
};
