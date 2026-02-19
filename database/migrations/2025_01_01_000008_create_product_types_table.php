<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('has_variants')->default(false);
            $table->boolean('has_ean')->default(false);
            $table->boolean('has_prices')->default(false);
            $table->boolean('has_media')->default(false);
            $table->boolean('has_stock')->default(false);
            $table->boolean('has_physical_dimensions')->default(false);
            $table->json('default_attribute_groups')->nullable();
            $table->json('allowed_relation_types')->nullable();
            $table->json('validation_rules')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_types');
    }
};
