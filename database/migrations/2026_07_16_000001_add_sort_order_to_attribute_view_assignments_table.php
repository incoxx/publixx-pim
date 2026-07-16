<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_view_assignments', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('attribute_view_id');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_view_assignments', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
