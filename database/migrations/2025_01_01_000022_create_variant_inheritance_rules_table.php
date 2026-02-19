<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_inheritance_rules', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('product_id', 36);
            $table->char('attribute_id', 36);
            $table->enum('inheritance_mode', ['inherit', 'override']);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->unique(['product_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_inheritance_rules');
    }
};
