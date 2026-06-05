<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergebnis der Konformitätsprüfung eines Produkts gegen sein Referenz-Profil.
 *
 * Pro Produkt wird nur das LETZTE Ergebnis gehalten (unique product_id), damit
 * aggregierte Reports über den gesamten Bestand effizient möglich sind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_conformance_results', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->nullable()
                ->constrained('product_reference_profiles')->nullOnDelete();

            // Version des Profils zum Prüfzeitpunkt → erkennt veraltete Prüfungen
            $table->unsignedInteger('profile_version')->default(1);

            $table->string('status', 20);          // pass | warning | fail
            $table->unsignedTinyInteger('score');  // 0..100

            $table->unsignedInteger('deviation_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('info_count')->default(0);

            // Detaillierte Abweichungen (Input für KI-Erklärung in Schritt 2)
            $table->json('deviations')->nullable();

            $table->string('trigger', 20)->default('manual'); // save | manual | batch
            $table->timestamp('checked_at');

            $table->timestamps();

            $table->unique('product_id');
            $table->index('profile_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_conformance_results');
    }
};
