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
            $table->char('parent_assignment_id', 36)->nullable()->after('access_variant');
            $table->foreign('parent_assignment_id')
                ->references('id')->on('hierarchy_node_attribute_assignments')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('hierarchy_node_attribute_assignments', function (Blueprint $table) {
            $table->dropForeign(['parent_assignment_id']);
            $table->dropColumn('parent_assignment_id');
        });
    }
};
