<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Macht aus der Begriffsliste einen Terminologiebestand.
 *
 * Alle Felder sind optional — der Bestand umfasst mehrere tausend Begriffe,
 * ein Pflichtfeld wuerde nie gepflegt. Gefuellt wird dort, wo es sich lohnt:
 * bei mehrdeutigen Begriffen und bei allem, was gar nicht uebersetzt werden
 * darf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tms_units', function (Blueprint $table) {
            // Technische Bezeichner, Normangaben (DIN, IP65), Einheiten und
            // Markennamen. Ohne dieses Kennzeichen werden sie in jede
            // Zielsprache maschinell uebersetzt — kostenpflichtig und falsch.
            $table->boolean('do_not_translate')->default(false)->index()->after('domain');

            // Bedeutung fuer Uebersetzer UND als Kontext fuer die Maschine.
            // Bei ein- bis dreiwortigen Benennungen ohne Satzkontext ist das
            // der groesste Qualitaetshebel.
            $table->text('definition')->nullable()->after('do_not_translate');

            // noun, verb, adjective, adverb, abbreviation, proper_noun
            $table->string('word_class', 20)->nullable()->after('definition');

            // Synonym-Verweis auf die Vorzugsbenennung: "Batterie" -> "Akku".
            // Der Begriff behaelt seine eigene Unit (er kommt so aus dem PIM),
            // erbt aber die Uebersetzungen der Vorzugsbenennung und wird nicht
            // eigenstaendig maschinell uebersetzt.
            $table->char('preferred_unit_id', 36)->nullable()->index()->after('word_class');

            $table->foreign('preferred_unit_id')
                ->references('id')->on('tms_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tms_units', function (Blueprint $table) {
            $table->dropForeign(['preferred_unit_id']);
            $table->dropColumn(['do_not_translate', 'definition', 'word_class', 'preferred_unit_id']);
        });
    }
};
