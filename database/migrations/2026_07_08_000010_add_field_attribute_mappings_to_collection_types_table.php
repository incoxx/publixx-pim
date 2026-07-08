<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ersetzt das fragile Suffix-Matching in CollectionRenderService::findValueByTechnicalNameSuffix()
     * (str_ends_with gegen '-rabatt'/'-positionstext'/'-zahlungsbedingungen'/'-kopftext', nur fuer
     * die Namenskonvention des Demo-Typs 'angebot' funktionsfaehig) durch eine explizite, pro
     * Collection-Typ konfigurierbare technical_name-Zuordnung -- gleiches Muster wie das bereits
     * bestehende default_price_type. Kein FK (Attribute werden im ganzen Repo per technical_name
     * aufgeloest, nicht per ID-Join).
     */
    public function up(): void
    {
        Schema::table('collection_types', function (Blueprint $table) {
            $table->string('default_discount_attribute', 100)->nullable()->after('default_price_type');
            $table->string('default_line_text_attribute', 100)->nullable()->after('default_discount_attribute');
            $table->string('default_payment_terms_attribute', 100)->nullable()->after('default_line_text_attribute');
            $table->string('default_cover_text_attribute', 100)->nullable()->after('default_payment_terms_attribute');
        });
    }

    public function down(): void
    {
        Schema::table('collection_types', function (Blueprint $table) {
            $table->dropColumn([
                'default_discount_attribute',
                'default_line_text_attribute',
                'default_payment_terms_attribute',
                'default_cover_text_attribute',
            ]);
        });
    }
};
