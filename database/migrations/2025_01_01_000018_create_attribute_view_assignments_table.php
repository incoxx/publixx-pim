<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_view_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('attribute_id', 36);
            $table->char('attribute_view_id', 36);
            $table->timestamps();

            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('attribute_view_id')->references('id')->on('attribute_views')->onDelete('cascade');
            $table->unique(['attribute_id', 'attribute_view_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_view_assignments');
    }
};
