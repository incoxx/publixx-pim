<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Textarea zum ENUM hinzufügen
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','MultiSelection','Dictionary','Composite','RichText',
                'Hyperlink','ImageLink','PdfLink','VideoLink',
                'DelimitedValue','JsonArtefact','Textarea'
            ) NOT NULL");
        }

        // Zeilen/Spalten-Konfiguration für Textarea-Attribute
        Schema::table('attributes', function (Blueprint $table) {
            $table->unsignedSmallInteger('textarea_rows')->nullable()->after('delimiter');
            $table->unsignedSmallInteger('textarea_cols')->nullable()->after('textarea_rows');
        });
    }

    public function down(): void
    {
        // Textarea-Attribute zu String konvertieren
        DB::table('attributes')
            ->where('data_type', 'Textarea')
            ->update(['data_type' => 'String']);

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn(['textarea_rows', 'textarea_cols']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','MultiSelection','Dictionary','Composite','RichText',
                'Hyperlink','ImageLink','PdfLink','VideoLink',
                'DelimitedValue','JsonArtefact'
            ) NOT NULL");
        }
    }
};
