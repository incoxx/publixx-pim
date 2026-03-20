<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->string('workflow_status', 20)->default('open')->after('status');
            $table->uuid('assigned_to')->nullable()->after('updated_by');
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->index('assigned_to');
            $table->index('workflow_status');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['workflow_status']);
            $table->dropColumn(['workflow_status', 'assigned_to']);
        });
    }
};
