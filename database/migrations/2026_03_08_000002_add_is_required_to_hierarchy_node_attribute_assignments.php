<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hierarchy_node_attribute_assignments', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('dont_inherit');
        });
    }

    public function down(): void
    {
        Schema::table('hierarchy_node_attribute_assignments', function (Blueprint $table) {
            $table->dropColumn('is_required');
        });
    }
};
