<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_cart_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('technical_name', 100)->unique();
            $table->string('name_de', 255);
            $table->string('name_en', 255)->nullable();
            $table->json('name_json')->nullable();

            // Verhaltenstyp: einfache Liste, PDF-Liste oder vollständiger Warenkorb
            $table->enum('type', ['wishlist', 'pdf_list', 'ecommerce'])->default('ecommerce');

            // Preistyp für Ecommerce-Carts (Pflicht wenn type=ecommerce und show_prices=true)
            $table->uuid('price_type_id')->nullable();
            $table->foreign('price_type_id')->references('id')->on('price_types')->nullOnDelete();

            // Anzeigeoptionen
            $table->boolean('show_prices')->default(false);
            $table->boolean('show_quantity')->default(false);
            $table->boolean('show_total')->default(false);
            $table->boolean('allow_checkout')->default(false);

            // Übermittlungs-Adapter
            $table->enum('adapter_type', ['none', 'email', 'rest_api'])->default('none');
            $table->json('adapter_config')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_cart_types');
    }
};
