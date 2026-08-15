<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inhaltssprachen des PIM.
 *
 * Bisher gab es dafuer keine Verwaltung — die Sprachen standen ausschliesslich
 * in der Env-Variable TMS_TARGET_LANGUAGES, und zwar doppelt: in der PIM-.env
 * und in tms/.env. Wer eine Sprache ergaenzen wollte, musste beide Dateien
 * anfassen; vergass er eine, liefen Uebersetzung und Rueckschreiben still
 * auseinander.
 *
 * Diese Tabelle ist ab jetzt die Quelle der Wahrheit. Die Env-Variable bleibt
 * als Fallback fuer Installationen, die noch nicht migriert sind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // ISO-639-1, z.B. 'de', 'en', 'fi'. Identitaet der Sprache —
            // dieser Wert landet als target_lang im TMS und als `language`
            // an den Attributwerten.
            $table->string('technical_name', 5)->unique();

            $table->string('name_de');
            $table->string('name_en')->nullable();

            // Quellsprache: Ausgangspunkt jeder Uebersetzung. Genau eine.
            $table->boolean('is_source')->default(false);

            // Nur aktive Sprachen werden uebersetzt und zurueckgeschrieben.
            $table->boolean('is_active')->default(true);

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Bestand aus der Env uebernehmen, damit bestehende Installationen
        // nach dem Update nicht ohne Sprachen dastehen.
        $now = now();
        $rows = [];
        $sort = 0;

        $rows[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'technical_name' => 'de',
            'name_de' => 'Deutsch',
            'name_en' => 'German',
            'is_source' => true,
            'is_active' => true,
            'sort_order' => $sort++,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $known = [
            'en' => ['Englisch', 'English'],
            'fr' => ['Französisch', 'French'],
            'es' => ['Spanisch', 'Spanish'],
            'it' => ['Italienisch', 'Italian'],
            'nl' => ['Niederländisch', 'Dutch'],
            'fi' => ['Finnisch', 'Finnish'],
            'pl' => ['Polnisch', 'Polish'],
            'pt' => ['Portugiesisch', 'Portuguese'],
            'cs' => ['Tschechisch', 'Czech'],
            'da' => ['Dänisch', 'Danish'],
            'sv' => ['Schwedisch', 'Swedish'],
            'nb' => ['Norwegisch', 'Norwegian'],
            'hu' => ['Ungarisch', 'Hungarian'],
            'ro' => ['Rumänisch', 'Romanian'],
            'sk' => ['Slowakisch', 'Slovak'],
            'sl' => ['Slowenisch', 'Slovenian'],
            'bg' => ['Bulgarisch', 'Bulgarian'],
            'el' => ['Griechisch', 'Greek'],
            'tr' => ['Türkisch', 'Turkish'],
            'ru' => ['Russisch', 'Russian'],
            'ja' => ['Japanisch', 'Japanese'],
            'ko' => ['Koreanisch', 'Korean'],
            'zh' => ['Chinesisch', 'Chinese'],
            'et' => ['Estnisch', 'Estonian'],
            'lv' => ['Lettisch', 'Latvian'],
            'lt' => ['Litauisch', 'Lithuanian'],
            'uk' => ['Ukrainisch', 'Ukrainian'],
            'id' => ['Indonesisch', 'Indonesian'],
        ];

        $envLangs = array_filter(array_map(
            'trim',
            explode(',', (string) env('TMS_TARGET_LANGUAGES', 'en,fr,es,it,nl'))
        ));

        foreach ($envLangs as $code) {
            if ($code === 'de') {
                continue;
            }

            $rows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'technical_name' => $code,
                'name_de' => $known[$code][0] ?? strtoupper($code),
                'name_en' => $known[$code][1] ?? strtoupper($code),
                'is_source' => false,
                'is_active' => true,
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('languages')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
