<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_pages', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('pdf_document_id', 36);
            $table->unsignedInteger('page_number');
            $table->string('image_path', 500);
            $table->longText('extracted_text')->nullable();
            $table->timestamps();

            $table->foreign('pdf_document_id')->references('id')->on('pdf_documents')->cascadeOnDelete();
            $table->unique(['pdf_document_id', 'page_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_pages');
    }
};
