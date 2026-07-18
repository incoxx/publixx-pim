<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_relation_types', function (Blueprint $table) {
            $table->json('allowed_source_product_type_ids')->nullable()->after('is_bidirectional');
            $table->json('allowed_target_product_type_ids')->nullable()->after('allowed_source_product_type_ids');
        });
    }

    public function down(): void
    {
        Schema::table('product_relation_types', function (Blueprint $table) {
            $table->dropColumn(['allowed_source_product_type_ids', 'allowed_target_product_type_ids']);
        });
    }
};
