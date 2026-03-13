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
            $table->enum('workflow_status', ['editing', 'review', 'approved'])->nullable()->default(null)->after('status');
            $table->char('workflow_assignee_id', 36)->nullable()->after('workflow_status');
            $table->foreign('workflow_assignee_id')->references('id')->on('users')->nullOnDelete();
            $table->index('workflow_status');
        });

        Schema::table('product_types', function (Blueprint $table) {
            $table->boolean('workflow_enabled')->default(false)->after('has_physical_dimensions');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['workflow_assignee_id']);
            $table->dropIndex(['workflow_status']);
            $table->dropColumn(['workflow_status', 'workflow_assignee_id']);
        });

        Schema::table('product_types', function (Blueprint $table) {
            $table->dropColumn('workflow_enabled');
        });
    }
};
