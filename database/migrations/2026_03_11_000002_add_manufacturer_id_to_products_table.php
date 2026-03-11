<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('manufacturer_id')->nullable()->after('master_hierarchy_node_id')->constrained('manufacturers')->nullOnDelete();
            $table->index('manufacturer_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['manufacturer_id']);
            $table->dropIndex(['manufacturer_id']);
            $table->dropColumn('manufacturer_id');
        });
    }
};
