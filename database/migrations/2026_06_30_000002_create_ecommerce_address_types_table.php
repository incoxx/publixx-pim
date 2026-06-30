<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_address_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();

            // Semantische Rolle: Rechnung, Versand oder allgemein
            $table->enum('role', ['billing', 'shipping', 'general'])->default('billing');

            // Felddefinitionen: [{key, label_de, label_en, type, required, sort_order}]
            $table->json('field_schema')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_address_types');
    }
};
