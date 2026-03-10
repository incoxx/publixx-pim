<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_usage_types', function (Blueprint $table) {
            $table->json('allowed_extensions')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('media_usage_types', function (Blueprint $table) {
            $table->dropColumn('allowed_extensions');
        });
    }
};
