<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_job_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('translation_job_id')->constrained('translation_jobs')->cascadeOnDelete();
            $table->string('entity_type'); // product, attribute, value_list_entry, hierarchy_node, etc.
            $table->uuid('entity_id');
            $table->uuid('attribute_id')->nullable(); // for product attributes
            $table->text('source_text');
            $table->text('translated_text')->nullable();
            $table->string('status')->default('pending'); // pending, translated, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('translation_job_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_job_items');
    }
};
