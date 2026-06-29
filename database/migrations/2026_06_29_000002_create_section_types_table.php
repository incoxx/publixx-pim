<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_types', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('category', 50)->nullable(); // text | media | commerce | layout
            // Feld-Schema: { "fields": [ {key,label,type,translatable,required,...}, ... ] }
            $table->json('schema');
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('is_nestable')->default(false);
            $table->string('preview_component', 100)->nullable();
            $table->boolean('is_system')->default(false); // im Code geseedet, nicht löschbar
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_types');
    }
};
