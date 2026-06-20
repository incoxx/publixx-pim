<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Admin-Editor: persistierte rollenspezifische Cockpit-Layouts.
 *
 * Speichert je Rolle ein Layout (welche Kacheln/Widgets je Zone, in welcher
 * Reihenfolge). Persönliche Layouts bleiben in user_preferences ('cockpit').
 * Auflösung im Frontend: persönlich → gespeichertes Rollen-Layout → Code-Default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cockpit_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Rolle, für die das Layout gilt (eindeutig). NULL = System-Override.
            $table->uuid('role_id')->nullable()->unique();
            // Layout-JSON: { tiles:[], workplace:[], content:[], kpis:[] }
            $table->json('layout');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cockpit_profiles');
    }
};
