<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_versions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('product_id', 36);
            $table->unsignedInteger('version_number');
            $table->enum('status', ['draft', 'scheduled', 'active', 'archived'])->default('draft');
            $table->json('snapshot');
            $table->text('change_reason')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->char('created_by', 36)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['product_id', 'status']);
            $table->index(['status', 'publish_at']);
            $table->unique(['product_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_versions');
    }
};
