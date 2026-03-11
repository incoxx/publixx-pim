<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','Dictionary','Composite','RichText',
                'Hyperlink','ImageLink','PdfLink','VideoLink'
            ) NOT NULL");
        }
    }

    public function down(): void
    {
        // Convert link attributes to String before removing enum values
        DB::table('attributes')
            ->whereIn('data_type', ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink'])
            ->update(['data_type' => 'String']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','Dictionary','Composite','RichText'
            ) NOT NULL");
        }
    }
};
