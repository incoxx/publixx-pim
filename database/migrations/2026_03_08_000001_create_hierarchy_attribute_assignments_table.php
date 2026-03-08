<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hierarchy_attribute_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hierarchy_id')->constrained('hierarchies')->cascadeOnDelete();
            $table->foreignUuid('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hierarchy_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hierarchy_attribute_assignments');
    }
};
