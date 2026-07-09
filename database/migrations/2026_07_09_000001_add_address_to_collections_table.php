<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Strukturierte Adresse fuer ein Angebot (Nutzeranfrage) -- lebt bewusst auf der Collection
     * selbst statt auf Organization (die bleibt eine duenne, schema-arme Render-Projektion mit
     * nur Freitext-address_block). Ein Angebot ist ein Point-in-Time-Dokument: die Adresse wird
     * beim Anlegen aus der verknuepften Organisation vorbefuellt, bleibt aber pro Collection
     * ueberschreibbar/eingefroren -- gleiches Prinzip wie ecommerce_orders.billing_address, das
     * vom Warenkorb zum Bestellzeitpunkt uebernommen wird, statt live auf den Kunden zu zeigen.
     *
     * Keine eigene Adress-Tabelle: das E-Commerce-"Blueprint" (ecommerce_carts/-orders) ist selbst
     * nur ein JSON-Blob pro Besitzer-Spalte, kein Modell mit polymorpher Owner-Relation -- es gibt
     * hier nichts strukturell Wiederverwendbares ausser der Konvention selbst.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->json('address')->nullable()->after('organization_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
