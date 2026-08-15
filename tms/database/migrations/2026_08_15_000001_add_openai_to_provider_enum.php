<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Das provider-ENUM kannte 'openai' nicht, obwohl der OpenAIProvider ueber
 * TMS_PROVIDER_CHAIN waehlbar ist. Jede von OpenAI gelieferte Uebersetzung
 * lief damit in einen Constraint-Fehler (MySQL: Data truncated, SQLite:
 * CHECK constraint failed) und ging verloren.
 *
 * Statt das ENUM erneut fest zu verdrahten wird auf VARCHAR umgestellt —
 * neue Provider brauchen dann keine Migration mehr. Die Validierung erfolgt
 * in ImportTranslationsController::ALLOWED_PROVIDERS.
 */
return new class extends Migration
{
    public function up(): void
    {
        // change() statt rohem SQL: Laravel 11 setzt das nativ auf MySQL und
        // SQLite um (bei SQLite durch Neuaufbau der Tabelle), damit die
        // Testsuite dieselbe Spaltendefinition sieht wie die Produktion.
        Schema::table('tms_translations', function (Blueprint $table) {
            $table->string('provider', 20)->default('deepl')->change();
        });
    }

    public function down(): void
    {
        // Werte, die im alten ENUM nicht existieren, vor dem Rueckbau angleichen
        DB::table('tms_translations')
            ->whereNotIn('provider', ['deepl', 'google', 'claude', 'human', 'import'])
            ->update(['provider' => 'import']);

        Schema::table('tms_translations', function (Blueprint $table) {
            $table->enum('provider', ['deepl', 'google', 'claude', 'human', 'import'])
                ->default('deepl')
                ->change();
        });
    }
};
