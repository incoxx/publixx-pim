<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pxf_templates', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->json('pxf_data')->nullable();
            $table->string('version', 10)->nullable();
            $table->enum('orientation', ['a4hoch', 'a4quer', 'custom'])->default('a4hoch');
            $table->char('product_type_id', 36)->nullable();
            $table->char('export_mapping_id', 36)->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('set null');
            $table->foreign('export_mapping_id')->references('id')->on('publixx_export_mappings')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pxf_templates');
    }
};
