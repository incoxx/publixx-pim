<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Duenne Render-Projektion eines Empfaengers -- KEIN CRM-Stammsatz.
     * System of Record fuer Kontakte/Aktivitaeten bleibt ERP/CRM (external_ref).
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('external_ref', 191)->nullable();
            $table->string('name', 500);
            $table->string('language', 5)->default('de');
            $table->text('address_block')->nullable();
            $table->char('logo_media_id', 36)->nullable();
            $table->string('price_list_ref', 100)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();

            $table->foreign('logo_media_id')->references('id')->on('media')->onDelete('set null');

            $table->index('external_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
