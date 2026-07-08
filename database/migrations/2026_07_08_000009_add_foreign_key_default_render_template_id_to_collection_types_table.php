<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PdfTemplates dienen Collections als Datenquelle fuer den Kopf-/Branding-Bereich
     * des gerenderten PDFs (CollectionPdfHeaderResolver). nullOnDelete, damit das Loeschen
     * einer PdfTemplate nicht den Collection-Typ blockiert -- der Kopfbereich faellt dann
     * einfach auf "keine Elemente" zurueck.
     */
    public function up(): void
    {
        Schema::table('collection_types', function (Blueprint $table) {
            $table->foreign('default_render_template_id')
                ->references('id')->on('pdf_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collection_types', function (Blueprint $table) {
            $table->dropForeign(['default_render_template_id']);
        });
    }
};
