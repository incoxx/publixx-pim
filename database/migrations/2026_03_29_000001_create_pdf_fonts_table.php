<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_fonts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('family_name', 255);

            // Pfade relativ zu storage/fonts/custom/
            $table->string('file_regular', 255)->nullable();
            $table->string('file_bold', 255)->nullable();
            $table->string('file_italic', 255)->nullable();
            $table->string('file_bold_italic', 255)->nullable();

            $table->char('user_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_fonts');
    }
};
