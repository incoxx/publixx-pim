<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publixx_export_mappings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->char('attribute_view_id', 36)->nullable();
            $table->char('output_hierarchy_id', 36)->nullable();
            $table->json('mapping_rules')->nullable();
            $table->boolean('include_media')->default(false);
            $table->boolean('include_prices')->default(false);
            $table->boolean('include_variants')->default(false);
            $table->boolean('include_relations')->default(false);
            $table->json('languages')->nullable();
            $table->enum('flatten_mode', ['flat', 'nested', 'publixx'])->default('flat');
            $table->timestamps();

            $table->foreign('attribute_view_id')->references('id')->on('attribute_views')->onDelete('set null');
            $table->foreign('output_hierarchy_id')->references('id')->on('hierarchies')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publixx_export_mappings');
    }
};
