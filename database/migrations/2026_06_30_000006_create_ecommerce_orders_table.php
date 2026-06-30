<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Lesbare Bestellnummer, z.B. ORD-2026-001234
            $table->string('order_number', 50)->unique();

            // Referenz auf den Warenkorb (kann null sein wenn Cart bereinigt wurde)
            $table->uuid('cart_id')->nullable();
            $table->foreign('cart_id')->references('id')->on('ecommerce_carts')->nullOnDelete();

            $table->uuid('cart_type_id');
            $table->foreign('cart_type_id')->references('id')->on('ecommerce_cart_types')->restrictOnDelete();

            // Denormalisiert für Audit-Trail
            $table->string('session_id', 255);
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->enum('status', ['pending', 'processing', 'confirmed', 'failed'])->default('pending');

            // Vollständiger Datensnapshot zum Bestellzeitpunkt
            $table->json('items_snapshot');
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();

            $table->uuid('payment_type_id')->nullable();
            $table->foreign('payment_type_id')->references('id')->on('ecommerce_payment_types')->nullOnDelete();

            $table->decimal('total_amount', 12, 2)->nullable();
            $table->char('currency', 3)->default('EUR');

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Adapter-Protokoll (Email-Versand oder REST-API-Antwort)
            $table->json('adapter_log')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'cart_type_id']);
            $table->index(['user_id', 'submitted_at']);
            $table->index(['session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_orders');
    }
};
