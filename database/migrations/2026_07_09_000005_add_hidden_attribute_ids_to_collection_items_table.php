<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Erlaubt das Abwaehlen einzelner Attribute der zugeordneten Positions-Attributsicht pro
     * Position -- nicht jede Position eines Angebots braucht zwangslaeufig jedes Attribut der
     * Sicht (z.B. "Farbe" bei einer Position ohne Farbvariante). Additiv zur Sicht: die Sicht
     * bestimmt, WELCHE Attribute ueberhaupt moeglich sind, hidden_attribute_ids blendet davon
     * pro Position welche aus -- weder Werte noch die Sichtzuordnung selbst werden geloescht.
     */
    public function up(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->json('hidden_attribute_ids')->nullable()->after('snapshot_at');
        });
    }

    public function down(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->dropColumn('hidden_attribute_ids');
        });
    }
};
