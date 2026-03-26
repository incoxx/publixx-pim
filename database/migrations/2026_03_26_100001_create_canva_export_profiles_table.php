<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canva_export_profiles', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->char('user_id', 36)->nullable();
            $table->boolean('is_shared')->default(false);

            // Verbindung
            $table->char('connector_connection_id', 36)->nullable();

            // Produktauswahl
            $table->char('search_profile_id', 36)->nullable();
            $table->json('product_ids')->nullable();

            // Datenauswahl
            $table->json('attribute_ids')->nullable();
            $table->json('languages')->nullable();
            $table->boolean('include_prices')->default(false);
            $table->boolean('include_media')->default(true);

            // Canva-spezifisch
            $table->string('brand_template_id', 255)->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('connector_connection_id')->references('id')->on('connector_connections')->nullOnDelete();
            $table->foreign('search_profile_id')->references('id')->on('search_profiles')->nullOnDelete();
            $table->index(['user_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canva_export_profiles');
    }
};
