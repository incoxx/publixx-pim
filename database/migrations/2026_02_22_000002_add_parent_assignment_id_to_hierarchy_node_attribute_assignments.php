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
            if (!Schema::hasColumn('hierarchy_node_attribute_assignments', 'parent_assignment_id')) {
                $table->char('parent_assignment_id', 36)->nullable()->after('access_variant');
            }

            $table->foreign('parent_assignment_id', 'hnaa_parent_assignment_fk')
                ->references('id')
                ->on('hierarchy_node_attribute_assignments')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('hierarchy_node_attribute_assignments', function (Blueprint $table) {
            $table->dropForeign('hnaa_parent_assignment_fk');
            $table->dropColumn('parent_assignment_id');
        });
    }
};
