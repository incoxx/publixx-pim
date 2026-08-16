<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadaten-Definitionen für Attribute (Data Quality & Ownership).
 *
 * Frei definierbare Metadaten-Felder wie "Datenherkunft", "Dateneigentümer"
 * oder "Datenverbindung", die anschließend je Attributdefinition gepflegt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_metadata_definitions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->text('description')->nullable();
            // Bewusst varchar statt ENUM: die ENUM-Spalte attributes.data_type musste
            // bereits mehrfach per ALTER TABLE erweitert werden (MySQL/SQLite-Doppelstrategie).
            // Gültige Werte: AttributeMetadataDefinition::VALUE_TYPES
            $table->string('value_type', 30)->default('text');
            // Auswahloptionen für value_type select/multiselect (Format wie attributes.simple_options)
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_metadata_definitions');
    }
};
