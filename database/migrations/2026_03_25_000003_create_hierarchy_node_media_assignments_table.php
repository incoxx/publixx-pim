<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hierarchy_node_media_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hierarchy_node_id')
                ->constrained('hierarchy_nodes')
                ->cascadeOnDelete();
            $table->foreignUuid('media_id')
                ->constrained('media')
                ->cascadeOnDelete();
            $table->foreignUuid('usage_type_id')
                ->nullable()
                ->constrained('media_usage_types')
                ->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['hierarchy_node_id', 'media_id'], 'hnma_node_media_unique');
            $table->index('hierarchy_node_id', 'hnma_node_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hierarchy_node_media_assignments');
    }
};
