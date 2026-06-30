<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cart_id');
            $table->foreign('cart_id')->references('id')->on('ecommerce_carts')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            // Bei wishlist-Typ immer 1, bei ecommerce editierbar
            $table->decimal('quantity', 10, 3)->default(1);

            // Preissnapshot zum Zeitpunkt des Hinzufügens
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->char('currency', 3)->nullable()->default('EUR');
            $table->json('price_snapshot')->nullable();

            $table->text('note')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            // Kein doppeltes Produkt im selben Warenkorb
            $table->unique(['cart_id', 'product_id']);
            $table->index(['cart_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_cart_items');
    }
};
