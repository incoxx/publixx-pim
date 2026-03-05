<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_profiles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->char('search_profile_id', 36)->nullable();
            $table->boolean('include_products')->default(true);
            $table->boolean('include_attributes')->default(true);
            $table->boolean('include_hierarchies')->default(false);
            $table->boolean('include_prices')->default(false);
            $table->boolean('include_relations')->default(false);
            $table->boolean('include_media')->default(false);
            $table->boolean('include_variants')->default(false);
            $table->json('attribute_ids')->nullable();
            $table->json('languages')->nullable();
            $table->enum('format', ['excel', 'csv', 'json', 'xml'])->default('excel');
            $table->string('file_name_template', 255)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('search_profile_id')->references('id')->on('search_profiles')->nullOnDelete();
            $table->index(['user_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_profiles');
    }
};
