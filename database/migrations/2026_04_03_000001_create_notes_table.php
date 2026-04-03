<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('color', 20)->default('yellow'); // yellow, blue, green, pink, purple, orange
            $table->boolean('pinned')->default(false);
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['created_by', 'pinned', 'sort_order']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
