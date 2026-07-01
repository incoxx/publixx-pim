<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vererbungsregeln eines virtuellen Produkts ("Klammer") pro Attribut.
 *
 * Der Wert selbst wird ganz normal im Attribut-Tab des virtuellen Produkts
 * gepflegt (product_attribute_values). Die Regel legt nur fest, WELCHE
 * Attribute beim Sync an die Mitglieder vererbt werden und WIE bei einem
 * bereits lokal gepflegten Wert auf dem Mitglied verfahren wird:
 *   - keep_local:     lokaler Wert des Mitglieds bleibt unangetastet
 *   - force_override: virtueller Wert überschreibt den lokalen Wert
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_product_inheritance_rules', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('virtual_product_id', 36);
            $table->char('attribute_id', 36);
            $table->enum('conflict_mode', ['keep_local', 'force_override'])->default('keep_local');
            $table->timestamps();

            $table->foreign('virtual_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->unique(['virtual_product_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_product_inheritance_rules');
    }
};
