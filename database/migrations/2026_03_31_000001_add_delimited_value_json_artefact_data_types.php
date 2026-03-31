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
        // Neue Datentypen zur ENUM-Spalte hinzufügen
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','Dictionary','Composite','RichText',
                'Hyperlink','ImageLink','PdfLink','VideoLink',
                'DelimitedValue','JsonArtefact'
            ) NOT NULL");
        }

        // Delimiter-Spalte für DelimitedValue-Attribute
        Schema::table('attributes', function (Blueprint $table) {
            $table->string('delimiter', 10)->nullable()->default(null)->after('composite_expression');
        });
    }

    public function down(): void
    {
        // Bestehende DelimitedValue/JsonArtefact-Attribute zu String konvertieren
        DB::table('attributes')
            ->whereIn('data_type', ['DelimitedValue', 'JsonArtefact'])
            ->update(['data_type' => 'String']);

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('delimiter');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','Dictionary','Composite','RichText',
                'Hyperlink','ImageLink','PdfLink','VideoLink'
            ) NOT NULL");
        }
    }
};
