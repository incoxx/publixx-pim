<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assignment_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('original_file_name', 500);
            $table->string('file_name', 255);
            $table->uuid('asset_folder_id')->nullable();
            $table->uuid('usage_type_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->uuid('old_media_id')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamp('deleted_at');
            $table->timestamps();

            $table->index(['original_file_name', 'asset_folder_id'], 'idx_history_filename_folder');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assignment_history');
    }
};
