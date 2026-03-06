<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing Collection attributes to String
        DB::table('attributes')
            ->where('data_type', 'Collection')
            ->update(['data_type' => 'String']);

        // Remove Collection, add RichText
        DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
            'String','Number','Float','Date','Flag','Selection','Dictionary','Composite','RichText'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
            'String','Number','Float','Date','Flag','Selection','Dictionary','Collection','Composite','RichText'
        ) NOT NULL");
    }
};
