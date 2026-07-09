<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * default_line_text_attribute/default_payment_terms_attribute/default_cover_text_attribute
     * waren Einzelauswahl-Zuordnungen (genau ein Attribut pro Rolle). Das deckt sich nicht mit
     * dem Nutzer-Feedback, dass eine Collection-Position mehrere frei waehlbare, geordnete
     * Attribute anzeigen koennen soll -- genau das leisten Attributgruppen (AttributeView)
     * bereits an anderer Stelle im PIM. default_attribute_groups/default_item_attribute_groups
     * existierten zwar schon als Spalten, wurden aber nirgends ausgewertet (totes Config-Feld).
     * Diese Migration entfernt die drei Einzelrollen-Spalten zugunsten der Gruppen-Aufloesung
     * in CollectionRenderService/AttributeController (filter[attribute_view]).
     * default_discount_attribute bleibt bestehen -- geht rechnerisch in die Positionssumme ein,
     * ist also keine reine Anzeige-Zuordnung wie die drei entfernten Felder.
     */
    public function up(): void
    {
        Schema::table('collection_types', function (Blueprint $table) {
            $table->dropColumn([
                'default_line_text_attribute',
                'default_payment_terms_attribute',
                'default_cover_text_attribute',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('collection_types', function (Blueprint $table) {
            $table->string('default_line_text_attribute', 100)->nullable()->after('default_discount_attribute');
            $table->string('default_payment_terms_attribute', 100)->nullable()->after('default_line_text_attribute');
            $table->string('default_cover_text_attribute', 100)->nullable()->after('default_payment_terms_attribute');
        });
    }
};
