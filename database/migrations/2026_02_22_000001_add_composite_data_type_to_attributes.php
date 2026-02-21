<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
            'String','Number','Float','Date','Flag','Selection','Dictionary','Collection','Composite'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attributes MODIFY COLUMN data_type ENUM(
            'String','Number','Float','Date','Flag','Selection','Dictionary','Collection'
        ) NOT NULL");
    }
};
