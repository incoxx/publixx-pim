<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_templates', function (Blueprint $table) {
            $table->string('output_format', 20)->default('json')->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('api_templates', function (Blueprint $table) {
            $table->dropColumn('output_format');
        });
    }
};
