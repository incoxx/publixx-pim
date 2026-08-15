<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anzeige-Fallback je Sprache.
 *
 * Fehlt im Produkteditor ein Wert in der gewaehlten Sprache, zeigt der Editor
 * den Wert der Fallback-Sprache ausgegraut als Orientierung an — z.B. Finnisch
 * faellt auf Englisch zurueck.
 *
 * WICHTIG: Das ist eine reine Anzeige. Es wird NICHTS gespeichert, solange der
 * Redakteur den Wert nicht ausdruecklich uebernimmt. Wuerde man automatisch
 * kopieren, waere das Feld anschliessend nicht mehr als "fehlend" erkennbar:
 * TranslationJobService::collectProductItems() ueberspringt gefuellte
 * Zielsprachen, und der XLIFF-Export setzt state="translated". Der englische
 * Text wuerde dann dauerhaft als finnische Uebersetzung durchgehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            // Referenz ueber den ISO-Code statt ueber die ID: der Code ist
            // ohnehin die fachliche Identitaet und taucht so in der ganzen
            // Anwendung auf.
            $table->string('fallback_language', 5)->nullable()->after('is_active');
        });

        // Sinnvolle Startbelegung: alles ausser der Quellsprache faellt auf
        // Englisch zurueck, Englisch auf die Quellsprache. Wer es anders will,
        // stellt es in der Oberflaeche um.
        $source = DB::table('languages')->where('is_source', true)->value('technical_name') ?? 'de';
        $hasEnglish = DB::table('languages')->where('technical_name', 'en')->exists();

        if ($hasEnglish) {
            DB::table('languages')
                ->where('is_source', false)
                ->where('technical_name', '!=', 'en')
                ->update(['fallback_language' => 'en']);

            DB::table('languages')
                ->where('technical_name', 'en')
                ->update(['fallback_language' => $source]);
        } else {
            DB::table('languages')
                ->where('is_source', false)
                ->update(['fallback_language' => $source]);
        }
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('fallback_language');
        });
    }
};
