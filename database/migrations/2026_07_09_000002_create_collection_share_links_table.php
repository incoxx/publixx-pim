<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passwortgeschuetzter, oeffentlicher Freigabe-Link auf eine Collection (z.B. ein Angebot),
     * als Browser-HTML statt PDF-Download -- Nutzeranfrage: "eine Seite rendern, mit Passwort
     * geschuetzt, die ich einem Kunden als Link schicken kann". Passwort wird manuell beim
     * Erstellen vergeben (nicht automatisch generiert) und separat (Telefon/E-Mail) mitgeteilt.
     *
     * Kein FK-Praezedenzfall existiert im Repo fuer "oeffentlicher Freigabe-Link" (naechstes
     * Aehnliches: access_links, aber das ist ein Einladungslink der Benutzerkonten anlegt --
     * zweckfremd). Struktur (UUID-PK, zufaelliges Token, expires_at) folgt trotzdem der dortigen
     * Konvention.
     */
    public function up(): void
    {
        Schema::create('collection_share_links', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('collection_id', 36);
            $table->string('token', 64)->unique();
            $table->string('password_hash');
            $table->timestamp('expires_at')->nullable();
            $table->char('created_by', 36)->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_share_links');
    }
};
