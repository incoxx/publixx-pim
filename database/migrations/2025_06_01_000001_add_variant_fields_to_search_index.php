<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products_search_index', function (Blueprint $table) {
            $table->string('product_type_ref', 20)->nullable()->after('product_type');
            $table->char('parent_product_id', 36)->nullable()->after('product_type_ref');

            $table->index('product_type_ref', 'idx_type_ref');
        });
    }

    public function down(): void
    {
        Schema::table('products_search_index', function (Blueprint $table) {
            $table->dropIndex('idx_type_ref');
            $table->dropColumn(['product_type_ref', 'parent_product_id']);
        });
    }
};
