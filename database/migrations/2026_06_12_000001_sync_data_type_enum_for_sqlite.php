<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die bisherigen ENUM-Erweiterungen für data_type liefen nur unter MySQL
     * (ALTER TABLE ... MODIFY COLUMN). Unter SQLite (Test-Umgebung) blieb der
     * ursprüngliche CHECK-Constraint bestehen, wodurch neuere Datentypen wie
     * DelimitedValue, JsonArtefact, Textarea und MultiSelection abgelehnt wurden.
     *
     * Diese Migration gleicht den Constraint unter Nicht-MySQL-Treibern auf den
     * aktuellen Stand an. Unter MySQL ist sie ein No-Op.
     */
    private const DATA_TYPES = [
        'String', 'Number', 'Float', 'Date', 'Flag', 'Selection', 'MultiSelection',
        'Dictionary', 'Composite', 'RichText',
        'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink',
        'DelimitedValue', 'JsonArtefact', 'Textarea',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            return;
        }

        Schema::table('attributes', function (Blueprint $table) {
            $table->enum('data_type', self::DATA_TYPES)->change();
        });
    }

    public function down(): void
    {
        // Kein Rollback nötig — der Constraint entspricht dem MySQL-Stand.
    }
};
