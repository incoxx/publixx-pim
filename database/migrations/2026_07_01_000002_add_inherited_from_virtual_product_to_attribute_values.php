<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markiert Attributwerte, die per Sync von einem virtuellen Produkt
 * ("Klammer") an ein Mitglied vererbt wurden. Zusammen mit is_inherited
 * (bereits vorhanden) ergibt sich die Herkunft eines Werts: manuell
 * gepflegt (is_inherited=false) vs. vom virtuellen Produkt X vererbt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->char('inherited_from_virtual_product_id', 36)->nullable()->after('inherited_from_product_id');
            $table->foreign('inherited_from_virtual_product_id')
                ->references('id')->on('products')->onDelete('set null');
            $table->index('inherited_from_virtual_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropForeign(['inherited_from_virtual_product_id']);
            $table->dropColumn('inherited_from_virtual_product_id');
        });
    }
};
