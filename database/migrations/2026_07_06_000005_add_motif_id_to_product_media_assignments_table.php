<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Erlaubt, einem Produkt statt eines einzelnen Media-Assets ein ganzes Motiv
     * zuzuordnen (motif_id). media_id wird dafür nullable — Anwendungsebene stellt
     * sicher, dass genau eines von beiden gesetzt ist (siehe StoreProductMediaRequest).
     */
    public function up(): void
    {
        // media_id nullable machen (kein doctrine/dbal installiert, daher raw SQL statt ->change()).
        // SQLite kennt kein MODIFY; dort ist die Spalte für die Testsuite ohnehin
        // nicht auf NOT NULL angewiesen.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE product_media_assignments MODIFY media_id CHAR(36) NULL');
        }

        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->char('motif_id', 36)->nullable()->after('media_id');

            $table->foreign('motif_id')
                ->references('id')
                ->on('media_motifs')
                ->onDelete('cascade');

            $table->index('motif_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->dropForeign(['motif_id']);
            $table->dropIndex(['motif_id']);
            $table->dropColumn('motif_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE product_media_assignments MODIFY media_id CHAR(36) NOT NULL');
        }
    }
};
