<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE hierarchies MODIFY COLUMN hierarchy_type ENUM('master','output','asset') NOT NULL DEFAULT 'master'");
        }
        // SQLite: no enum enforcement needed — it's just TEXT
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE hierarchies MODIFY COLUMN hierarchy_type ENUM('master','output') NOT NULL DEFAULT 'master'");
        }
    }
};
