<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->char('output_hierarchy_id', 36)->nullable()->default(null)->after('inherited_from_product_id');

            $table->foreign('output_hierarchy_id')
                ->references('id')
                ->on('hierarchies')
                ->onDelete('cascade');

            // Drop old unique constraint
            $table->dropUnique('pav_product_attr_lang_idx_unique');

            // New unique constraint including output_hierarchy_id
            $table->unique(
                ['product_id', 'attribute_id', 'language', 'multiplied_index', 'output_hierarchy_id'],
                'pav_product_attr_lang_idx_oh_unique'
            );

            // Covering index for output hierarchy queries
            $table->index(['product_id', 'output_hierarchy_id'], 'idx_pav_product_output_hierarchy');
        });
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropIndex('idx_pav_product_output_hierarchy');
            $table->dropUnique('pav_product_attr_lang_idx_oh_unique');
            $table->dropForeign(['output_hierarchy_id']);
            $table->dropColumn('output_hierarchy_id');

            $table->unique(
                ['product_id', 'attribute_id', 'language', 'multiplied_index'],
                'pav_product_attr_lang_idx_unique'
            );
        });
    }
};
