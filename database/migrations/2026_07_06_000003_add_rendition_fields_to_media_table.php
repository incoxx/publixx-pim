<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rendition-Felder auf media. Alle Spalten sind nullable/default-behaftet:
     * Bestehende Zeilen (motif_id = NULL) verhalten sich exakt wie zuvor
     * ("einfache Lösung" für reine Web-Nutzung ohne Motiv-Gruppierung).
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->char('motif_id', 36)->nullable()->after('asset_folder_id');
            $table->boolean('is_master_rendition')->default(false)->after('motif_id');
            $table->string('rendition_channel', 20)->nullable()->after('is_master_rendition'); // print, web, mobile, social
            $table->char('rendition_preset_id', 36)->nullable()->after('rendition_channel');
            $table->string('colorspace', 10)->nullable()->after('rendition_preset_id'); // rgb, cmyk, gray
            $table->unsignedInteger('dpi')->nullable()->after('colorspace');
            $table->json('crop_data')->nullable()->after('dpi'); // {x,y,w,h} fraktional 0..1, relativ zum Master
            $table->timestamp('generated_at')->nullable()->after('crop_data'); // NULL = manuell hochgeladen

            $table->foreign('motif_id')
                ->references('id')
                ->on('media_motifs')
                ->onDelete('cascade');

            $table->foreign('rendition_preset_id')
                ->references('id')
                ->on('media_rendition_presets')
                ->onDelete('set null');

            $table->index('motif_id');
            $table->index(['motif_id', 'rendition_channel']);
            $table->unique(['motif_id', 'rendition_preset_id'], 'media_motif_preset_unique');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique('media_motif_preset_unique');
            $table->dropForeign(['motif_id']);
            $table->dropForeign(['rendition_preset_id']);
            $table->dropIndex(['motif_id']);
            $table->dropIndex(['motif_id', 'rendition_channel']);
            $table->dropColumn([
                'motif_id',
                'is_master_rendition',
                'rendition_channel',
                'rendition_preset_id',
                'colorspace',
                'dpi',
                'crop_data',
                'generated_at',
            ]);
        });
    }
};
