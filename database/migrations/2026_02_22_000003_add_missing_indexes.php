<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->index('attribute_id', 'pav_attribute_id_index');
        });

        Schema::table('product_versions', function (Blueprint $table) {
            $table->index('status', 'pv_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropIndex('pav_attribute_id_index');
        });

        Schema::table('product_versions', function (Blueprint $table) {
            $table->dropIndex('pv_status_index');
        });
    }
};
