<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markiert Medien-Zuordnungen, die per Sync von einem virtuellen Produkt
 * ("Klammer") an ein Mitglied vererbt wurden — Pendant zu
 * product_attribute_values.inherited_from_virtual_product_id, aber für
 * product_media_assignments.
 */
return new class extends Migration
{
    /**
     * Explizite, kurze Namen für FK/Index — der von Laravel automatisch
     * generierte Foreign-Key-Name
     * ("product_media_assignments_inherited_from_virtual_product_id_foreign",
     * 74 Zeichen) überschreitet MySQLs 64-Zeichen-Limit für Identifier.
     */
    private const FK_NAME = 'pma_inherited_from_virtual_product_foreign';
    private const INDEX_NAME = 'pma_inherited_from_virtual_product_index';

    public function up(): void
    {
        if (!Schema::hasColumn('product_media_assignments', 'inherited_from_virtual_product_id')) {
            Schema::table('product_media_assignments', function (Blueprint $table) {
                $table->char('inherited_from_virtual_product_id', 36)->nullable();
            });
        }

        $indexes = collect(Schema::getIndexes('product_media_assignments'))->pluck('name');
        $needsForeign = !$indexes->contains(self::FK_NAME);
        $needsIndex = !$indexes->contains(self::INDEX_NAME);

        if ($needsForeign || $needsIndex) {
            Schema::table('product_media_assignments', function (Blueprint $table) use ($needsForeign, $needsIndex) {
                if ($needsForeign) {
                    $table->foreign('inherited_from_virtual_product_id', self::FK_NAME)
                        ->references('id')->on('products')->onDelete('set null');
                }
                if ($needsIndex) {
                    $table->index('inherited_from_virtual_product_id', self::INDEX_NAME);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_media_assignments', function (Blueprint $table) {
            $table->dropForeign(self::FK_NAME);
            $table->dropColumn('inherited_from_virtual_product_id');
        });
    }
};
