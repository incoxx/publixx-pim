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
                'String','Number','Float','Date','Flag','Selection','Dictionary','Collection','Composite'
            ) NOT NULL");
        }
        // SQLite: ENUM is stored as TEXT, no column modification needed
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
                'String','Number','Float','Date','Flag','Selection','Dictionary','Collection'
            ) NOT NULL");
        }
    }
};
