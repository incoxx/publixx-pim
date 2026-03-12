<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_templates', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Template-Definition (Canvas-Elemente mit x/y/w/h/type/style)
            $table->json('template_json');

            // Seiten-Einstellungen
            $table->enum('page_orientation', ['portrait', 'landscape'])->default('portrait');
            $table->string('page_size', 20)->default('A4');

            // Besitzer
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_templates');
    }
};
