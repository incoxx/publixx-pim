<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medien-Vererbungsregeln eines virtuellen Produkts ("Klammer") pro
 * Usage-Type — Pendant zu virtual_product_inheritance_rules, aber für
 * Medien statt Attribute.
 *
 * Die Medien-Zuordnungen selbst werden ganz normal im Medien-Tab des
 * virtuellen Produkts gepflegt (product_media_assignments). Die Regel legt
 * nur fest, für WELCHE Usage-Types beim Sync die Zuordnungen des
 * virtuellen Produkts an die Mitglieder vererbt werden und WIE bei
 * bereits lokal gepflegten Zuordnungen des Mitglieds für diesen Usage-Type
 * verfahren wird:
 *   - keep_local:     lokale Zuordnungen des Mitglieds bleiben unangetastet
 *   - force_override: vererbte Zuordnungen ersetzen die lokalen
 */
return new class extends Migration
{
    private const UNIQUE_INDEX = 'vpmir_virtual_product_usage_type_unique';

    /**
     * Explizite, kurze FK-Namen — der von Laravel automatisch generierte
     * Name ("virtual_product_media_inheritance_rules_virtual_product_id_foreign",
     * 68 Zeichen) überschreitet MySQLs 64-Zeichen-Limit für Identifier.
     */
    private const FK_VIRTUAL_PRODUCT = 'vpmir_virtual_product_foreign';
    private const FK_USAGE_TYPE = 'vpmir_usage_type_foreign';

    public function up(): void
    {
        Schema::create('virtual_product_media_inheritance_rules', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('virtual_product_id', 36);
            $table->char('usage_type_id', 36);
            $table->enum('conflict_mode', ['keep_local', 'force_override'])->default('keep_local');
            $table->timestamps();

            $table->foreign('virtual_product_id', self::FK_VIRTUAL_PRODUCT)->references('id')->on('products')->onDelete('cascade');
            $table->foreign('usage_type_id', self::FK_USAGE_TYPE)->references('id')->on('media_usage_types')->onDelete('cascade');
            $table->unique(['virtual_product_id', 'usage_type_id'], self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_product_media_inheritance_rules');
    }
};
