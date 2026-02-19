<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('unit_group_id', 36);
            $table->string('technical_name', 100);
            $table->string('abbreviation', 20);
            $table->json('abbreviation_json')->nullable();
            $table->decimal('conversion_factor', 20, 10)->default(1);
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_translatable')->default(false);
            $table->timestamps();

            $table->foreign('unit_group_id')->references('id')->on('unit_groups')->onDelete('cascade');
            $table->unique(['unit_group_id', 'technical_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
