<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_profiles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->char('product_type_id', 36)->nullable();
            $table->string('sku_column', 100)->default('SKU');
            $table->json('column_mappings');
            $table->json('price_mappings')->nullable();
            $table->json('relation_mappings')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('product_type_id')->references('id')->on('product_types')->nullOnDelete();
            $table->index(['user_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_profiles');
    }
};
