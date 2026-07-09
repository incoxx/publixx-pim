<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additiver Scope: auf welchen Entitaetsebenen ein Attribut waehlbar ist
     * (product / collection / collection_item). Default deckt alle Bestandsattribute ab.
     *
     * MySQL 8 erlaubt auf JSON/TEXT/BLOB-Spalten keinen literalen DEFAULT-Wert (Error 1101) --
     * der Default muss als geklammerter Ausdruck angegeben werden. Ein einfacher String-Default
     * (frueher: ->default(json_encode(...))) waere daher auf echtem MySQL 8 ein Migrationsfehler,
     * obwohl er auf der lockereren MariaDB-JSON-Emulation durchlaeuft.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->json('applies_to')->default(new Expression("('" . json_encode(['product']) . "')"));
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
