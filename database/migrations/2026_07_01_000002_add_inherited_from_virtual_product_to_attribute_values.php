<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markiert Attributwerte, die per Sync von einem virtuellen Produkt
 * ("Klammer") an ein Mitglied vererbt wurden. Zusammen mit is_inherited
 * (bereits vorhanden) ergibt sich die Herkunft eines Werts: manuell
 * gepflegt (is_inherited=false) vs. vom virtuellen Produkt X vererbt.
 */
return new class extends Migration
{
    /**
     * Explizite, kurze Namen für FK/Index — der von Laravel automatisch
     * generierte Foreign-Key-Name
     * ("product_attribute_values_inherited_from_virtual_product_id_foreign",
     * 66 Zeichen) überschreitet MySQLs 64-Zeichen-Limit für Identifier.
     */
    private const FK_NAME = 'pav_inherited_from_virtual_product_foreign';
    private const INDEX_NAME = 'pav_inherited_from_virtual_product_index';

    public function up(): void
    {
        // product_attribute_values ist die größte EAV-Tabelle im System
        // (skaliert mit Produkte × Attribute × Sprachen). Absichtlich KEIN
        // ->after(...): eine positionierte Spalte erzwingt auf MySQL < 8.0.29
        // bzw. MariaDB einen vollen Tabellen-Rebuild statt der instant-DDL-
        // Optimierung für ans Ende angehängte Spalten — auf großen Instanzen
        // kann das sonst sehr lange dauern ("hängt" beim Deploy).
        if (!Schema::hasColumn('product_attribute_values', 'inherited_from_virtual_product_id')) {
            Schema::table('product_attribute_values', function (Blueprint $table) {
                $table->char('inherited_from_virtual_product_id', 36)->nullable();
            });
        }

        // FK + Index in einem ALTER TABLE zusammenfassen (statt zwei
        // getrennten Table-Scans) — reduziert die Laufzeit auf großen Tabellen.
        $indexes = collect(Schema::getIndexes('product_attribute_values'))->pluck('name');
        $needsForeign = !$indexes->contains(self::FK_NAME);
        $needsIndex = !$indexes->contains(self::INDEX_NAME);

        if ($needsForeign || $needsIndex) {
            Schema::table('product_attribute_values', function (Blueprint $table) use ($needsForeign, $needsIndex) {
                if ($needsForeign) {
                    $table->foreign('inherited_from_virtual_product_id', self::FK_NAME)
                        ->references('id')->on('products')->onDelete('set null');
                }
                if ($needsIndex) {
                    $table->index('inherited_from_virtual_product_id', self::INDEX_NAME);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropForeign(self::FK_NAME);
            $table->dropColumn('inherited_from_virtual_product_id');
        });
    }
};
