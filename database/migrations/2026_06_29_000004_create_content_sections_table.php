<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sections', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('content_page_id', 36);
            $table->char('section_type_id', 36);
            $table->char('parent_section_id', 36)->nullable(); // für nestable (Spalten/Accordion)
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            // Feldwerte je Sprache: { "de": {...}, "en": {...} }, sprachneutrale unter "_"
            $table->json('values_json')->nullable();
            // Layout/Style: { background, padding, anchor, css_class }
            $table->json('settings_json')->nullable();
            $table->timestamps();

            $table->foreign('content_page_id')->references('id')->on('content_pages')->onDelete('cascade');
            $table->foreign('section_type_id')->references('id')->on('section_types')->onDelete('restrict');
            $table->foreign('parent_section_id')->references('id')->on('content_sections')->onDelete('cascade');

            $table->index(['content_page_id', 'sort_order']);
            $table->index(['parent_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_sections');
    }
};
