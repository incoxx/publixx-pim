<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cart_type_id');
            $table->foreign('cart_type_id')->references('id')->on('ecommerce_cart_types')->restrictOnDelete();

            // SHA-256-Hash des pim_session-Cookies — kein Klartext-Token in der DB
            $table->string('session_id', 255);

            // Optional: eingeloggter Benutzer (wird beim Merge nach Login gesetzt)
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->enum('status', ['active', 'submitted', 'abandoned', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            // Ausgefüllte Adressdaten (erst beim Checkout befüllt)
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();

            $table->uuid('payment_type_id')->nullable();
            $table->foreign('payment_type_id')->references('id')->on('ecommerce_payment_types')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Pro Session+Typ nur ein aktiver Warenkorb
            $table->unique(['cart_type_id', 'session_id']);
            $table->index(['session_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_carts');
    }
};
