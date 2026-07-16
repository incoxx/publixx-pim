<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_views', function (Blueprint $table) {
            $table->boolean('show_as_tab')->default(false)->after('is_write_protected');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_views', function (Blueprint $table) {
            $table->dropColumn('show_as_tab');
        });
    }
};
