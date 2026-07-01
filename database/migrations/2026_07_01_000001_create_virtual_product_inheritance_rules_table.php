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
    /**
     * Name des Unique-Index. MySQL erlaubt max. 64 Zeichen für Identifier —
     * der von Laravel automatisch generierte Name
     * ("virtual_product_inheritance_rules_virtual_product_id_attribute_id_unique",
     * 72 Zeichen) überschreitet das Limit, daher ein expliziter, kurzer Name.
     */
    private const UNIQUE_INDEX = 'vpir_virtual_product_attribute_unique';

    public function up(): void
    {
        // Falls ein früherer Migrationsversuch bereits an obigem zu langem
        // Indexnamen scheiterte, existiert die Tabelle unter Umständen schon
        // (CREATE TABLE lief durch, der nachfolgende ADD UNIQUE schlug fehl).
        // In dem Fall nur den fehlenden Index nachtragen statt neu anzulegen.
        if (Schema::hasTable('virtual_product_inheritance_rules')) {
            $hasIndex = collect(Schema::getIndexes('virtual_product_inheritance_rules'))
                ->pluck('name')
                ->contains(self::UNIQUE_INDEX);

            if (!$hasIndex) {
                Schema::table('virtual_product_inheritance_rules', function (Blueprint $table) {
                    $table->unique(['virtual_product_id', 'attribute_id'], self::UNIQUE_INDEX);
                });
            }

            return;
        }

        Schema::create('virtual_product_inheritance_rules', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('virtual_product_id', 36);
            $table->char('attribute_id', 36);
            $table->enum('conflict_mode', ['keep_local', 'force_override'])->default('keep_local');
            $table->timestamps();

            $table->foreign('virtual_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->unique(['virtual_product_id', 'attribute_id'], self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_product_inheritance_rules');
    }
};
