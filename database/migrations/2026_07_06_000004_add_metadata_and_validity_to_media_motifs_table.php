<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gängige DAM-/IPTC-Metadaten (Urheber, Bildnachweis, Schlagworte) sowie ein
     * optionaler Gültigkeitszeitraum (z.B. saisonale Kampagnenbilder) — unabhängig
     * von license_valid_until, das die rechtliche Lizenzlaufzeit abbildet.
     */
    public function up(): void
    {
        Schema::table('media_motifs', function (Blueprint $table) {
            $table->string('creator', 255)->nullable()->after('rights_holder');
            $table->string('credit_line', 255)->nullable()->after('creator');
            $table->json('keywords')->nullable()->after('usage_restrictions');
            $table->date('valid_from')->nullable()->after('keywords');
            $table->date('valid_until')->nullable()->after('valid_from');

            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('media_motifs', function (Blueprint $table) {
            $table->dropIndex(['valid_until']);
            $table->dropColumn(['creator', 'credit_line', 'keywords', 'valid_from', 'valid_until']);
        });
    }
};
