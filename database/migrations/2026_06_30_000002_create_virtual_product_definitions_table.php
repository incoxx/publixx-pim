<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Definition eines virtuellen Produkts (dynamischer Cluster).
 *
 * Verknüpft das Klammer-Produkt (product_id) mit der Quelle, aus der seine
 * Mitglieder live aufgelöst werden:
 *   - search_profile → gespeichertes Suchprofil
 *   - pql            → freie PQL-Abfrage
 *   - manual         → fixe Produktliste (z.B. aus der Merkliste übernommen)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_product_definitions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('product_id', 36)->unique();
            $table->enum('source_type', ['search_profile', 'pql', 'manual']);
            $table->char('search_profile_id', 36)->nullable();
            $table->text('pql_query')->nullable();
            $table->json('manual_product_ids')->nullable();
            $table->char('relation_type_id', 36)->nullable();
            $table->string('language', 10)->default('de');
            $table->unsignedInteger('max_members')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('search_profile_id')->references('id')->on('search_profiles')->onDelete('set null');
            $table->foreign('relation_type_id')->references('id')->on('product_relation_types')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_product_definitions');
    }
};
