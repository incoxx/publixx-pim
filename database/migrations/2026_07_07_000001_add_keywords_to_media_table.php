<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Freie Schlagwörter pro Medium (unabhängig vom title/description) — durchsuchbar/filterbar
     * und über Autocomplete aus bereits vergebenen Werten pflegbar. Gleiches Muster wie
     * media_motifs.keywords (siehe 2026_07_06_000004_add_metadata_and_validity_to_media_motifs_table).
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->json('keywords')->nullable()->after('alt_text_en');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('keywords');
        });
    }
};
