<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->bigInteger('file_size')->unsigned();
            $table->enum('media_type', ['image', 'document', 'video', 'other'])->default('other');
            $table->string('title_de', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->text('description_de')->nullable();
            $table->text('description_en')->nullable();
            $table->string('alt_text_de', 255)->nullable();
            $table->string('alt_text_en', 255)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
