<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('content_type_id', 36);
            $table->string('slug', 255);
            $table->string('title', 500);
            $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('draft');

            // Gültigkeitszeitraum (zeitgesteuerte Sichtbarkeit)
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();

            // Hinweis: KEINE navigation_node_id — Platzierung erfolgt 1:n über navigation_nodes
            // (Output-Muster, siehe docs/architecture/22-structured-content.md §3.3).

            // SEO
            $table->json('seo_title_json')->nullable();
            $table->json('seo_description_json')->nullable();
            $table->char('seo_image_id', 36)->nullable();

            // Workflow (analog Product)
            $table->char('workflow_id', 36)->nullable();
            $table->char('current_workflow_status_id', 36)->nullable();
            $table->char('workflow_assignee_id', 36)->nullable();

            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();

            $table->foreign('content_type_id')->references('id')->on('content_types')->onDelete('restrict');
            $table->foreign('seo_image_id')->references('id')->on('media')->onDelete('set null');
            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('set null');

            $table->index('content_type_id');
            $table->index('status');
            $table->index(['valid_from', 'valid_to']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
