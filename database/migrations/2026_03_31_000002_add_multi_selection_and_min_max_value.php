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
        // MultiSelection zum ENUM hinzufügen
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','MultiSelection','Dictionary','Composite','RichText',
                'Hyperlink','ImageLink','PdfLink','VideoLink',
                'DelimitedValue','JsonArtefact'
            ) NOT NULL");
        }

        // Min/Max-Wertebereich für Number/Float-Attribute
        Schema::table('attributes', function (Blueprint $table) {
            $table->decimal('min_value', 20, 6)->nullable()->after('max_characters');
            $table->decimal('max_value', 20, 6)->nullable()->after('min_value');
        });
    }

    public function down(): void
    {
        // MultiSelection-Attribute zu Selection konvertieren
        DB::table('attributes')
            ->where('data_type', 'MultiSelection')
            ->update(['data_type' => 'Selection']);

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn(['min_value', 'max_value']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','Dictionary','Composite','RichText',
                'Hyperlink','ImageLink','PdfLink','VideoLink',
                'DelimitedValue','JsonArtefact'
            ) NOT NULL");
        }
    }
};
