<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_profiles', function (Blueprint $table) {
            $table->json('attribute_filter_groups')->nullable()->after('attribute_filters');
        });
    }

    public function down(): void
    {
        Schema::table('search_profiles', function (Blueprint $table) {
            $table->dropColumn('attribute_filter_groups');
        });
    }
};
