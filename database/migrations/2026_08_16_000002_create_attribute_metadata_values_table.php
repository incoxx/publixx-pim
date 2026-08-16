<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadatenwerte je Attributdefinition.
 *
 * Bewusst kein großes EAV-Schema (value_string/value_number/...) wie bei
 * product_attribute_values: hier gibt es weder Sortier-, Einheiten- noch
 * Sprachanforderungen. Skalare Werte landen in `value`, Mehrfachauswahl in `value_json`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_metadata_values', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('attribute_id', 36);
            $table->char('definition_id', 36);
            $table->text('value')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->cascadeOnDelete();
            $table->foreign('definition_id')
                ->references('id')->on('attribute_metadata_definitions')
                ->cascadeOnDelete();

            $table->unique(['attribute_id', 'definition_id']);
            $table->index('definition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_metadata_values');
    }
};
