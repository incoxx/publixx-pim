<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Virtuelle Referenzprodukte (Referenz-Profile).
 *
 * Ein Profil beschreibt eine Produktgruppe (z.B. "Akkubohrschrauber") vollständig
 * und legt Prüfregeln für Attributwerte fest. Produkte referenzieren ein Profil
 * explizit (Scope am Produkt). Profile können voneinander erben (parent_profile_id),
 * abstrakte Profile dienen nur als Basis und sind nicht direkt referenzierbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reference_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('technical_name', 191)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Vererbung: Kind erbt Regeln vom Eltern-Profil (Kind gewinnt bei Konflikt)
            $table->foreignUuid('parent_profile_id')->nullable()
                ->constrained('product_reference_profiles')->nullOnDelete();

            // Abstrakte Profile sind nur Basis und nicht direkt referenzierbar
            $table->boolean('is_abstract')->default(false);
            $table->boolean('is_active')->default(true);

            // Versionierung: wird bei Regeländerung erhöht → triggert Batch-Re-Check
            $table->unsignedInteger('version')->default(1);

            // Prüfregeln (nur die EIGENEN; Merge mit Eltern zur Laufzeit)
            $table->json('rules')->nullable();

            // Echte Vorbild-Produkte als KI-Kontext (Schritt 2)
            $table->json('golden_product_ids')->nullable();

            $table->timestamps();

            $table->index('parent_profile_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reference_profiles');
    }
};
