<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->char('asset_folder_id', 36)->nullable()->after('height');
            $table->string('usage_purpose', 10)->default('both')->after('asset_folder_id');

            $table->foreign('asset_folder_id')
                ->references('id')
                ->on('hierarchy_nodes')
                ->onDelete('set null');

            $table->index('asset_folder_id');
            $table->index('usage_purpose');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropForeign(['asset_folder_id']);
            $table->dropIndex(['asset_folder_id']);
            $table->dropIndex(['usage_purpose']);
            $table->dropColumn(['asset_folder_id', 'usage_purpose']);
        });
    }
};
