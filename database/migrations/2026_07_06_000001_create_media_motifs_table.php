<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motiv-Ebene: fasst mehrere Renditions (Print-TIFF, Web-JPEG, Mobile-WebP, ...)
     * desselben Bildinhalts zu einer Einheit zusammen. Bestehende media-Zeilen ohne
     * motif_id bleiben unberührt ("einfache Lösung" für reine Web-Nutzung).
     */
    public function up(): void
    {
        Schema::create('media_motifs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('title_de', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->text('description_de')->nullable();
            $table->text('description_en')->nullable();

            // Fokuspunkt für automatischen Cover-Crop je Ausgabeformat (0..1 relativ zur Bildfläche)
            $table->decimal('focal_point_x', 5, 4)->nullable();
            $table->decimal('focal_point_y', 5, 4)->nullable();

            // Rechte-/Lizenzverwaltung
            $table->string('rights_holder', 255)->nullable();
            $table->string('license_type', 100)->nullable();
            $table->date('license_valid_until')->nullable();
            $table->string('copyright_notice', 500)->nullable();
            $table->text('usage_restrictions')->nullable();

            $table->char('asset_folder_id', 36)->nullable();

            $table->timestamps();

            $table->foreign('asset_folder_id')
                ->references('id')
                ->on('hierarchy_nodes')
                ->onDelete('set null');

            $table->index('asset_folder_id');
            $table->index('license_valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_motifs');
    }
};
