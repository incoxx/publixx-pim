<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vier neue Attribut-Datentypen:
     *  - HierarchyNodeReference: Querverweis auf einen Hierarchie-Knoten
     *  - ProductReference:       Querverweis auf ein anderes Produkt
     *  - SimpleSelect:           einfache Selectbox mit frei gepflegten Optionen
     *  - SimpleMultiSelect:      wie SimpleSelect, mehrfach
     *
     * Alle vier werden string-backed in value_string gespeichert
     * (Referenzen als UUID, SimpleMultiSelect als JSON-Array), daher keine
     * Änderung an den product_attribute_values-Tabellen nötig.
     *
     * Zusätzlich: Spalte simple_options (JSON) auf attributes für die frei
     * gepflegten Optionen von SimpleSelect/SimpleMultiSelect (analog zu
     * delimiter/textarea_rows als typspezifische Konfigurationsspalte).
     */
    private const array DATA_TYPES = [
        'String', 'Number', 'Float', 'Date', 'Flag', 'Selection', 'MultiSelection',
        'Dictionary', 'Composite', 'RichText',
        'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink',
        'DelimitedValue', 'JsonArtefact', 'Textarea',
        'HierarchyNodeReference', 'ProductReference', 'SimpleSelect', 'SimpleMultiSelect',
    ];

    private const array PREVIOUS_DATA_TYPES = [
        'String', 'Number', 'Float', 'Date', 'Flag', 'Selection', 'MultiSelection',
        'Dictionary', 'Composite', 'RichText',
        'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink',
        'DelimitedValue', 'JsonArtefact', 'Textarea',
    ];

    private const array NEW_DATA_TYPES = [
        'HierarchyNodeReference', 'ProductReference', 'SimpleSelect', 'SimpleMultiSelect',
    ];

    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            if (!Schema::hasColumn('attributes', 'simple_options')) {
                $table->json('simple_options')->nullable()->after('textarea_cols');
            }
        });

        $this->setEnum(self::DATA_TYPES);
    }

    public function down(): void
    {
        // Neue Typen zu String konvertieren, bevor die Enum-Werte entfernt werden.
        DB::table('attributes')
            ->whereIn('data_type', self::NEW_DATA_TYPES)
            ->update(['data_type' => 'String']);

        $this->setEnum(self::PREVIOUS_DATA_TYPES);

        Schema::table('attributes', function (Blueprint $table): void {
            if (Schema::hasColumn('attributes', 'simple_options')) {
                $table->dropColumn('simple_options');
            }
        });
    }

    /**
     * Setzt den data_type-Enum treiberabhängig:
     *  - MySQL: ALTER TABLE ... MODIFY COLUMN (echtes ENUM)
     *  - SQLite u.a.: enum(...)->change() gleicht den CHECK-Constraint an
     *
     * @param array<int, string> $types
     */
    private function setEnum(array $types): void
    {
        if (DB::getDriverName() === 'mysql') {
            $quoted = implode(',', array_map(static fn (string $t): string => "'{$t}'", $types));
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM({$quoted}) NOT NULL");

            return;
        }

        Schema::table('attributes', function (Blueprint $table) use ($types): void {
            $table->enum('data_type', $types)->change();
        });
    }
};
