<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('media_id', 36)->unique();
            $table->string('original_url', 2048);
            $table->enum('status', ['pending', 'processing', 'ready', 'error'])->default('pending');
            $table->unsignedInteger('page_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_documents');
    }
};
