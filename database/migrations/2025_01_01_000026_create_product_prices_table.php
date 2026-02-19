<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('product_id', 36);
            $table->char('price_type_id', 36);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('country', 2)->nullable();
            $table->integer('scale_from')->nullable();
            $table->integer('scale_to')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('price_type_id')->references('id')->on('price_types')->onDelete('cascade');
            $table->index(['product_id', 'price_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
